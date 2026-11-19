import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:teacher_flutter/api.dart';
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
          home: Home(api: api),
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
}
