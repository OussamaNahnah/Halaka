import 'support/memory_draft_store.dart';

import 'dart:async';
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

void main() {
  testWidgets(
    'one session retains evaluations and blocks creation until matching acknowledgement',
    (tester) async {
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
      final payloads = <String>[];
      final delayed = Completer<http.Response>();
      http.Response response(Json data) => http.Response(
        jsonEncode(data),
        200,
        headers: {'content-type': 'application/json; charset=utf-8'},
      );
      final api = TeacherApi(
        client: MockClient((request) async {
          final path = request.url.path;
          if (request.method == 'POST' && path == '/api/sessions') {
            payloads.add(request.body);
            if (payloads.length == 1) {
              throw http.ClientException('Response lost');
            }
            if (payloads.length == 2) return delayed.future;
            final body = jsonDecode(request.body);
            return response({
              'submission_id': body['submission_id'],
              'data': {'id': 99, 'client_uuid': body['client_uuid']},
              'meta': {'sync_revision': 4},
            });
          }
          if (path == '/api/profile') {
            return response({
              'data': {
                'id': 1,
                'name': 'Teacher',
                'email': 'teacher@example.com',
              },
            });
          }
          if (path == '/api/sessions') {
            return response({
              'data': [],
              'meta': {'last_page': 1},
            });
          }
          if (path == '/api/groups') {
            return response({
              'data': [
                {'id': 1, 'name': 'النور', 'suggested': true},
              ],
            });
          }
          if (path == '/api/quran/surahs') {
            return response({
              'data': [
                {'name': 'الفاتحة', 'id': 1, 'verses': 7},
              ],
            });
          }
          if (path == '/api/groups/1/students') {
            return response({
              'data': [
                {'id': 10, 'name': 'أحمد'},
              ],
              'meta': {'sync_revision': 3},
            });
          }
          if (path == '/api/students/10/assignments') {
            return response({
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
            });
          }
          fail('Unexpected ${request.method} $path');
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: const [Locale('ar')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          theme: halakaTheme(Brightness.light),
          home: Home(api: api, draftStore: MemoryDraftStore()),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('إنشاء حصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('بدء'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('أحمد'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, '8.125');
      await tester.pumpAndSettle();
      // Back retains the grade without implicitly finishing the student.
      await tester.tap(find.byTooltip('طلاب الحصة'));
      await tester.pumpAndSettle();
      expect(find.text('لم يُقيَّم بعد'), findsOneWidget);
      await tester.tap(find.byTooltip('الحصص'));
      await tester.pumpAndSettle();
      expect(find.text('حصة جارية'), findsOneWidget);
      expect(find.text('إنشاء حصة'), findsNothing);
      await tester.tap(find.text('متابعة الحصة الحالية'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('أحمد'));
      await tester.pumpAndSettle();
      expect(find.text('8.125'), findsOneWidget);
      await tester.tap(find.text('إنهاء الطالب'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('إنهاء الحصة وإرسالها'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('تأكيد'));
      await tester.pumpAndSettle();
      expect(payloads.length, 1);
      expect(find.text('إلغاء التغييرات غير المرسلة'), findsNothing);
      await tester.tap(find.byTooltip('الحصص'));
      await tester.pumpAndSettle();
      expect(find.text('بانتظار الرفع'), findsOneWidget);
      expect(find.text('حذف'), findsNothing);
      expect(find.text('إنشاء حصة'), findsNothing);
      await tester.tap(find.text('بانتظار الرفع · عرض الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('إعادة محاولة الرفع'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(payloads.length, 2);
      expect(find.byType(CircularProgressIndicator), findsWidgets);
      await tester.tap(find.byTooltip('الحصص'));
      await tester.pump();
      expect(find.text('إنشاء حصة'), findsNothing);
      // An HTTP success without the matching durable acknowledgement is insufficient.
      delayed.complete(
        response({
          'data': {'id': 99, 'client_uuid': 'wrong'},
        }),
      );
      await tester.pumpAndSettle();
      expect(find.text('بانتظار الرفع'), findsOneWidget);
      expect(find.text('إنشاء حصة'), findsNothing);
      await tester.tap(find.text('بانتظار الرفع · عرض الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('إعادة محاولة الرفع'));
      await tester.pumpAndSettle();
      expect(payloads.length, 3);
      expect(payloads.toSet().length, 1);
      expect(find.text('إنشاء حصة'), findsOneWidget);
      expect((await SharedPreferences.getInstance()).getKeys(), {
        TeacherApi.authKey,
      });
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'conflicted local session can be deleted without changing server records',
    (tester) async {
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
      final draft = SessionDraft(
        payload: {
          'client_uuid': 'conflicted',
          'group_id': 1,
          'timestamp': '2026-11-20T10:00:00Z',
        },
        students: [],
        assignments: {},
      );
      draft.freeze();
      draft.conflict = true;
      await store.save('${TeacherApi.base}|1', {
        'draft': draft.toJson(),
        'groups': [
          {'id': 1, 'name': 'النور'},
        ],
        'surahs': [],
      });
      final api = TeacherApi(
        client: MockClient((request) async {
          expect(request.method, 'GET');
          return http.Response(
            jsonEncode(
              request.url.path.endsWith('/profile')
                  ? {
                      'data': {
                        'id': 1,
                        'name': 'Teacher',
                        'email': 'teacher@example.com',
                      },
                    }
                  : {
                      'data': [],
                      'meta': {'last_page': 1},
                    },
            ),
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
      await tester.tap(find.text('مراجعة الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('خيارات الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(PopupMenuItem<String>, 'حذف الحصة من الجهاز'),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('إلغاء'));
      await tester.pumpAndSettle();
      expect(await store.load('${TeacherApi.base}|1'), isNotNull);
      await tester.tap(find.text('حذف الحصة من الجهاز'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('تأكيد'));
      await tester.pumpAndSettle();
      expect(await store.load('${TeacherApi.base}|1'), isNull);
      expect(find.text('إنشاء حصة'), findsOneWidget);
      expect(find.text('تعارض يحتاج مراجعة'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
