import 'package:flutter/widgets.dart';
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
}
