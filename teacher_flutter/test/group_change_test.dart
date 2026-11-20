import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/main.dart';
import 'package:teacher_flutter/ui.dart';

import 'support/memory_draft_store.dart';

void main() {
  for (final evaluated in [false, true]) {
    testWidgets(
      'group replacement confirms only for evaluated students: $evaluated',
      (tester) async {
        final profile = {
          'id': 1,
          'name': 'Teacher',
          'email': 'teacher@example.com',
        };
        SharedPreferences.setMockInitialValues({
          TeacherApi.authKey: jsonEncode({'token': 'jwt', 'profile': profile}),
        });
        final store = MemoryDraftStore();
        final owner = '${TeacherApi.base}|1';
        final draft = SessionDraft(
          payload: {
            'group_id': 1,
            'timestamp': '2026-11-20T12:00:00Z',
            'client_uuid': 'stable-session',
            'base_sync_revision': 1,
          },
          students: [
            {
              'student_id': 1,
              'name': 'Old student',
              'evaluated_at': evaluated ? '2026-11-20T12:30:00Z' : null,
              'assessments': [
                {
                  'quran_assignment_id': 7,
                  'grade': 5,
                  'comment': 'Keep until confirmed',
                },
              ],
              'assignments': [],
            },
          ],
          assignments: {},
        );
        await store.save(owner, {
          'draft': draft.toJson(),
          'groups': [
            {'id': 1, 'name': 'Old group'},
            {'id': 2, 'name': 'New group'},
          ],
          'surahs': [],
        });
        var fail = true;
        var rosterCalls = 0;
        final api = TeacherApi(
          client: MockClient((request) async {
            if (request.url.path.endsWith('/profile')) {
              return http.Response(jsonEncode({'data': profile}), 200);
            }
            if (request.url.path.endsWith('/groups/2/students')) {
              rosterCalls++;
              if (fail) {
                fail = false;
                return http.Response(jsonEncode({'message': 'Offline'}), 503);
              }
              return http.Response(
                jsonEncode({
                  'data': [
                    {'id': 3, 'name': 'New student'},
                  ],
                  'meta': {'sync_revision': 4},
                }),
                200,
              );
            }
            if (request.url.path.endsWith('/students/3/assignments')) {
              return http.Response(
                jsonEncode({
                  'data': [
                    {'id': 9, 'is_passed': false},
                  ],
                  'meta': {'sync_revision': 4},
                }),
                200,
              );
            }
            return http.Response(
              jsonEncode({
                'data': [],
                'meta': {'last_page': 1},
              }),
              200,
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
        await tester.tap(find.text('متابعة الحصة الحالية'));
        await tester.pumpAndSettle();
        Future<void> changeGroup() async {
          await tester.tap(find.byTooltip('تعديل الحصة'));
          await tester.pumpAndSettle();
          await tester.tap(find.byType(DropdownButtonFormField<int>));
          await tester.pumpAndSettle();
          await tester.tap(find.text('New group').last);
          await tester.pumpAndSettle();
          await tester.tap(find.text('حفظ التعديل'));
          await tester.pumpAndSettle();
        }

        if (evaluated) {
          await changeGroup();
          await tester.tap(find.text('إلغاء'));
          await tester.pumpAndSettle();
          expect(rosterCalls, 0);
          expect(find.text('Old student'), findsOneWidget);
        }
        await changeGroup();
        if (evaluated) {
          await tester.tap(find.text('تأكيد'));
          await tester.pumpAndSettle();
        } else {
          expect(find.byType(AlertDialog), findsNothing);
        }
        expect(find.text('Old student'), findsOneWidget);
        expect(store.data[owner]!['draft']['payload']['group_id'], 1);
        await changeGroup();
        if (evaluated) {
          await tester.tap(find.text('تأكيد'));
          await tester.pumpAndSettle();
        }
        expect(find.text('New student'), findsOneWidget);
        expect(find.text('Old student'), findsNothing);
        final saved = store.data[owner]!['draft'];
        expect(saved['payload']['group_id'], 2);
        expect(saved['payload']['client_uuid'], isNot('stable-session'));
        expect(saved['students'].single['evaluated_at'], isNull);
        expect(saved['students'].single['assessments'], isEmpty);
        expect(saved['payload']['base_sync_revision'], 4);
        expect(saved['students'].single['student_id'], 3);
        expect(saved['assignments']['3'].single['id'], 9);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
