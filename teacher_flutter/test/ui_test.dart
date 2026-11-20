import 'support/memory_draft_store.dart';

import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/ui.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/main.dart';

void main() {
  testWidgets('Arabic RTL login renders on a narrow phone', (tester) async {
    SharedPreferences.setMockInitialValues({});
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const HalakaApp());
    await tester.pumpAndSettle();
    expect(find.text('تسجيل دخول الأستاذ'), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.text('تسجيل دخول الأستاذ'))),
      TextDirection.rtl,
    );
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'home footer avoids duplicate gesture padding and keeps a reachable action',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      tester.view.padding = const FakeViewPadding(bottom: 34);
      tester.view.viewPadding = const FakeViewPadding(bottom: 34);
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetPadding);
      addTearDown(tester.view.resetViewPadding);
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
      final api = TeacherApi(
        client: MockClient(
          (request) async => http.Response(
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
          ),
        ),
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
      final button = find.widgetWithText(FilledButton, 'إنشاء حصة');
      final buttonRect = tester.getRect(button);
      final tabsRect = tester.getRect(find.byType(NavigationBar));
      expect(buttonRect.height, greaterThanOrEqualTo(48));
      expect(tabsRect.top - buttonRect.bottom, inInclusiveRange(0, 8));
      expect(buttonRect.bottom, lessThan(844 - 34));
      await tester.tap(find.text('حسابي'));
      await tester.pumpAndSettle();
      expect(find.text('إنشاء حصة'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'saved session restores before network access and opens its evaluation offline',
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
      await store.save('${TeacherApi.base}|1', {
        'draft': {
          'id': null,
          'payload': {
            'client_uuid': 'session',
            'group_id': 1,
            'timestamp': '2026-11-20T10:00:00Z',
            'base_sync_revision': 3,
          },
          'students': [
            {
              'student_id': 10,
              'name': 'أحمد',
              'evaluated_at': null,
              'assignments': [],
              'assessments': [
                {
                  'client_uuid': 'attempt',
                  'quran_assignment_id': 7,
                  'grade': 8.125,
                },
              ],
            },
          ],
          'assignments': {
            '10': [
              {
                'id': 7,
                'surah_name': 'الفاتحة',
                'from_ayah': 1,
                'to_ayah': 7,
                'type': 'revision',
                'is_passed': false,
              },
            ],
          },
          'pending': null,
          'conflict': false,
        },
        'groups': [
          {'id': 1, 'name': 'النور'},
        ],
        'surahs': [
          {'id': 1, 'name': 'الفاتحة', 'verses': 7},
        ],
      });
      final api = TeacherApi(
        client: MockClient((_) async => throw http.ClientException('offline')),
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
      expect(find.text('إنشاء حصة'), findsNothing);
      expect(find.text('حصة جارية'), findsOneWidget);
      await tester.tap(find.text('النور'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('أحمد'));
      await tester.tap(find.text('أحمد'));
      await tester.pumpAndSettle();
      expect(find.text('8.125'), findsOneWidget);
      await tester.tap(find.byTooltip('طلاب الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('خيارات الحصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('حذف الحصة غير المرسلة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('إلغاء'));
      await tester.pumpAndSettle();
      expect(find.text('أحمد'), findsOneWidget);
      await tester.tap(find.byTooltip('الحصص'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('حذف'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('إلغاء'));
      await tester.pumpAndSettle();
      expect(find.text('حصة جارية'), findsOneWidget);
      await tester.tapAt(
        tester.getTopLeft(find.byKey(const ValueKey('active-session-card'))) +
            const Offset(12, 12),
      );
      await tester.pumpAndSettle();
      expect(find.text('أحمد'), findsOneWidget);
      await tester.tap(find.byTooltip('الحصص'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('حذف'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('تأكيد'));
      await tester.pumpAndSettle();
      expect(find.text('إنشاء حصة'), findsOneWidget);
      expect(await store.load('${TeacherApi.base}|1'), isNull);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'revoked token returns to normal login and restores the same teacher work',
    (tester) async {
      final profile = {
        'id': 1,
        'name': 'Teacher',
        'email': 'teacher@example.com',
      };
      SharedPreferences.setMockInitialValues({
        TeacherApi.authKey: jsonEncode({
          'token': 'revoked',
          'profile': profile,
        }),
      });
      final store = MemoryDraftStore();
      final draft = SessionDraft(
        payload: {
          'client_uuid': 'local',
          'group_id': 1,
          'timestamp': '2026-11-20T10:00:00Z',
        },
        students: [],
        assignments: {},
      );
      await store.save('${TeacherApi.base}|1', {
        'draft': draft.toJson(),
        'groups': [],
        'surahs': [],
      });
      final api = TeacherApi(
        client: MockClient((r) async {
          if (r.url.path.endsWith('/profile')) {
            return http.Response('revoked', 401);
          }
          if (r.url.path.endsWith('/login')) {
            return http.Response(
              jsonEncode({'access_token': 'new-token', 'data': profile}),
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
      expect(find.text('تسجيل دخول الأستاذ'), findsOneWidget);
      expect(find.text('تجديد تسجيل الدخول عند الحاجة'), findsNothing);
      expect(api.token, isNull);
      expect(
        (await SharedPreferences.getInstance()).getString(TeacherApi.authKey),
        isNull,
      );
      expect(await store.load('${TeacherApi.base}|1'), isNotNull);
      await tester.enterText(
        find.byType(TextField).first,
        'teacher@example.com',
      );
      await tester.enterText(find.byType(TextField).last, 'secret');
      await tester.ensureVisible(find.text('تسجيل الدخول'));
      await tester.tap(find.text('تسجيل الدخول'));
      await tester.pumpAndSettle();
      expect(find.text('حصة جارية'), findsOneWidget);
      expect(find.text('إنشاء حصة'), findsNothing);
      expect(api.token, 'new-token');
      expect(tester.takeException(), isNull);
    },
  );
}
