import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/main.dart';
import 'package:teacher_flutter/ui.dart';

import 'support/memory_draft_store.dart';

void main() {
  for (final hasActive in [false, true]) {
    testWidgets('accepted summary is read-only; active session=$hasActive', (
      tester,
    ) async {
      tester.view.physicalSize = Size(hasActive ? 320 : 390, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      SharedPreferences.setMockInitialValues({
        TeacherApi.authKey: jsonEncode({
          'token': 'jwt',
          'profile': {
            'id': 1,
            'name': 'Teacher',
            'email': 'teacher@example.com',
          },
        }),
      });
      final store = MemoryDraftStore();
      if (hasActive) {
        final draft = SessionDraft(
          payload: {
            'client_uuid': 'active-local',
            'group_id': 2,
            'timestamp': '2026-11-20T10:00:00Z',
          },
          students: [],
          assignments: {},
        );
        await store.save('${TeacherApi.base}|1', {
          'draft': draft.toJson(),
          'groups': [
            {'id': 2, 'name': 'الحصة الحالية'},
          ],
          'surahs': [],
        });
      }
      final original = jsonEncode(store.data);
      final session = <String, dynamic>{
        'id': 5,
        'client_uuid': 'accepted',
        'revision': 2,
        'group': {'id': 1, 'name': 'النور', 'archived': false},
        'timestamp': '2026-11-20T09:00:00Z',
        'status': 'completed',
        'assessed_assignments': [
          {
            'id': 7,
            'student_id': 10,
            'created_in_session_id': null,
            'surah_name': 'الفاتحة',
            'from_ayah': 1,
            'to_ayah': 7,
            'type': 'revision',
            'is_passed': false,
            'archived': true,
          },
        ],
        'created_assignments': [
          {
            'id': 9,
            'student_id': 10,
            'student_name': 'أحمد',
            'created_in_session_id': 5,
            'surah_name': 'الملك',
            'from_ayah': 1,
            'to_ayah': 10,
            'type': 'new_memorization',
            'is_passed': false,
            'archived': false,
          },
        ],
        'students': [
          {
            'student_id': 10,
            'name': 'أحمد',
            'attendance_status': 'present',
            'evaluated_at': '2026-11-20T09:10:00Z',
            'archived': false,
            'assessments': [
              {
                'id': 8,
                'quran_assignment_id': 7,
                'grade': 8.125,
                'comment': 'مراجعة موفقة',
                'is_passed': false,
              },
            ],
          },
          {
            'student_id': 20,
            'name': 'علي',
            'attendance_status': 'absent',
            'evaluated_at': null,
            'archived': false,
            'assessments': [],
          },
        ],
      };
      final calls = <String>[];
      final api = TeacherApi(
        client: MockClient((request) async {
          calls.add('${request.method} ${request.url.path}');
          expect(request.method, 'GET');
          final path = request.url.path;
          Json result;
          if (path == '/api/profile') {
            result = {
              'data': {
                'id': 1,
                'name': 'Teacher',
                'email': 'teacher@example.com',
              },
            };
          } else if (path == '/api/sessions') {
            result = {
              'data': [session],
              'meta': {'last_page': 1},
            };
          } else if (path == '/api/sessions/5') {
            result = {
              'data': session,
              'meta': {'sync_revision': 3},
            };
          } else if (path == '/api/groups') {
            result = {
              'data': [
                {'id': 1, 'name': 'النور', 'suggested': true},
              ],
            };
          } else if (path == '/api/quran/surahs') {
            result = {
              'data': [
                {'id': 1, 'name': 'الفاتحة', 'verses': 7},
              ],
            };
          } else if (path == '/api/students/10/assignments') {
            result = {
              'data': [
                {
                  'id': 7,
                  'surah_name': 'الفاتحة',
                  'from_ayah': 1,
                  'to_ayah': 7,
                  'type': 'revision',
                  'is_passed': false,
                },
              ],
              'meta': {'sync_revision': 3},
            };
          } else if (path == '/api/students/20/assignments') {
            result = {
              'data': [],
              'meta': {'sync_revision': 3},
            };
          } else {
            fail('Unexpected endpoint $path');
          }
          return http.Response(
            jsonEncode(result),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: const [Locale('ar')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          theme: halakaTheme(Brightness.light),
          home: Home(api: api, draftStore: store),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('النور'));
      await tester.pumpAndSettle();
      expect(find.text('ملخص الحصة'), findsOneWidget);
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey('summary-حاضر-count')))
            .data,
        '1',
      );
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey('summary-غائب-count')))
            .data,
        '1',
      );
      expect(find.text('حذف'), findsNothing);
      expect(find.byType(TextFormField), findsNothing);
      expect(jsonEncode(store.data), original);
      expect(calls, contains('GET /api/sessions/5'));
      expect(calls.any((call) => call.startsWith('GET /api/students/')), false);
      await tester.tap(find.text('أحمد'));
      await tester.pumpAndSettle();
      expect(find.text('8.125'), findsOneWidget);
      expect(find.text('مراجعة · الفاتحة'), findsOneWidget);
      expect(find.text('مراجعة موفقة'), findsOneWidget);
      expect(find.text('الواجبات الجديدة'), findsOneWidget);
      expect(find.textContaining('حفظ جديد · الملك'), findsOneWidget);
      expect(
        find.text('مراجعة · الفاتحة · الآيات 1–7\nقيد الإنجاز'),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
      if (hasActive) {
        expect(
          tester
              .widget<IconButton>(
                find.widgetWithIcon(IconButton, Icons.edit_outlined),
              )
              .onPressed,
          isNull,
        );
        await tester.tap(find.byTooltip('الحصص'));
        await tester.pumpAndSettle();
        expect(find.text('متابعة الحصة الحالية'), findsOneWidget);
        expect(jsonEncode(store.data), original);
      } else {
        await tester.tap(find.byTooltip('الحصص'));
        await tester.pumpAndSettle();
        expect(find.text('إنشاء حصة'), findsOneWidget);
        expect(store.data, isEmpty);
        await tester.tap(find.text('النور'));
        await tester.pumpAndSettle();
        await tester.tap(find.byTooltip('تعديل الحصة'));
        await tester.pumpAndSettle();
        expect(find.text('إنهاء وإرسال التعديلات'), findsOneWidget);
        expect(store.data.values.single['draft']['id'], 5);
        expect(
          store
              .data
              .values
              .single['draft']['students'][0]['assignments'][0]['id'],
          9,
        );
        expect(
          store.data.values.single['draft']['payload']['base_revision'],
          2,
        );
      }
      expect(tester.takeException(), isNull);
    });
  }
}
