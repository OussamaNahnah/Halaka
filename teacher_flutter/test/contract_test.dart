import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('default API uses the hosted server', () {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() => debugDefaultTargetPlatformOverride = null);
    expect(TeacherApi.base, 'https://h.oussamanh.com/api');
  });
  test('connection errors are readable and remain retryable', () async {
    final api = TeacherApi(
      client: MockClient(
        (_) async =>
            throw http.ClientException('SocketException localhost refused'),
      ),
    );
    await expectLater(
      api.request('GET', '/profile'),
      throwsA(
        isA<NetworkError>().having(
          (e) => e.toString(),
          'message',
          isNot(contains('SocketException')),
        ),
      ),
    );
  });
  test(
    'only authentication uses preferences and logout revokes bearer token',
    () async {
      SharedPreferences.setMockInitialValues({});
      final api = TeacherApi(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) {
            return http.Response(
              jsonEncode({
                'access_token': 'jwt',
                'data': {
                  'id': 1,
                  'teacher_id': 2,
                  'name': 'Teacher',
                  'email': 'teacher@example.com',
                },
              }),
              200,
            );
          }
          expect(request.headers['Authorization'], 'Bearer jwt');
          expect(request.url.path, endsWith('/logout'));
          return http.Response('{}', 200);
        }),
      );
      await api.login('teacher@example.com', 'secret');
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getKeys(), {TeacherApi.authKey});
      expect(
        jsonDecode(prefs.getString(TeacherApi.authKey)!).keys,
        unorderedEquals(['token', 'profile']),
      );
      expect(prefs.getString(TeacherApi.authKey), isNot(contains('secret')));
      final reopened = TeacherApi();
      await reopened.restore();
      expect(reopened.token, 'jwt');
      await api.logout();
      expect(prefs.getKeys(), isEmpty);
    },
  );
  test(
    'immutable retry preserves identities and excludes read-only server fields',
    () {
      final student = <String, dynamic>{
        'student_id': 1,
        'evaluated_at': '2026-11-19T10:00:00Z',
        'assignments': [],
        'assessments': [
          {
            'id': 5,
            'quran_assignment_id': 2,
            'grade': -1.125,
            'is_passed': false,
            'assignment_archived': false,
          },
        ],
      };
      final draft = SessionDraft(
        payload: {'client_uuid': uuid.v4(), 'base_sync_revision': 3},
        students: [
          student,
          {
            'student_id': 2,
            'evaluated_at': null,
            'assignments': [
              {'surah_name': 'الملك'},
            ],
            'assessments': [],
          },
        ],
        assignments: {},
      );
      student['assignments'] = [
        {
          'id': 9,
          'surah_name': 'الملك',
          'from_ayah': 1,
          'to_ayah': 10,
          'type': 'revision',
          'is_passed': false,
          'created_in_session_id': 3,
          'student_name': 'Student',
          'archived': false,
        },
      ];
      final first = jsonEncode(draft.freeze());
      student['assessments'][0]['grade'] = 99;
      expect(jsonEncode(draft.freeze()), first);
      final sent = draft.pending!['students'][0]['assessments'][0];
      expect(sent['grade'], -1.125);
      expect(sent['needs_repeat'], true);
      expect(sent.containsKey('assignment_archived'), false);
      expect(sent.containsKey('is_passed'), false);
      expect(
        draft.pending!['students'][0]['assignments'][0].keys,
        unorderedEquals([
          'id',
          'surah_name',
          'from_ayah',
          'to_ayah',
          'type',
          'is_passed',
        ]),
      );
      expect(draft.pending!['students'][1]['assignments'], isEmpty);
    },
  );
  test(
    'finishing rejects missing/nonfinite grades and does not invent zero',
    () {
      final student = <String, dynamic>{
        'student_id': 1,
        'assignments': [],
        'assessments': [
          {'grade': ''},
        ],
      };
      final draft = SessionDraft(
        payload: {},
        students: [student],
        assignments: {},
      );
      expect(draft.validateStudent(student), isNotNull);
      student['assessments'] = [
        {'grade': double.infinity},
      ];
      expect(draft.validateStudent(student), isNotNull);
      student['assessments'] = [
        {'grade': 0},
      ];
      expect(draft.validateStudent(student), isNull);
    },
  );
}
