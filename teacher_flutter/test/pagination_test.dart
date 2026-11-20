import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/main.dart';
import 'package:teacher_flutter/ui.dart';

import 'support/memory_draft_store.dart';

void main() {
  testWidgets(
    'pagination retries failed page without skipping and resets scroll',
    (tester) async {
      final profile = {
        'id': 1,
        'name': 'Teacher',
        'email': 'teacher@example.com',
      };
      SharedPreferences.setMockInitialValues({
        TeacherApi.authKey: jsonEncode({'token': 'jwt', 'profile': profile}),
      });
      final requested = <int>[];
      var fail = true;
      final api = TeacherApi(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/profile')) {
            return http.Response(jsonEncode({'data': profile}), 200);
          }
          final page = int.parse(request.url.queryParameters['page']!);
          requested.add(page);
          if (page == 2 && fail) {
            fail = false;
            return http.Response(jsonEncode({'message': 'Try again'}), 503);
          }
          return http.Response(
            jsonEncode({
              'data': [
                for (var i = 0; i < 12; i++)
                  {
                    'id': page * 100 + i,
                    'group': {'name': 'Page $page item $i'},
                    'timestamp': '2026-11-20T12:00:00Z',
                    'status': 'completed',
                  },
              ],
              'meta': {'last_page': 2, 'current_page': page},
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
          home: Home(api: api, draftStore: MemoryDraftStore()),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('التالي'), 400);
      await tester.tap(find.text('التالي'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('التالي'), 200);
      expect(find.text('1 / 2'), findsOneWidget);
      await tester.tap(find.text('التالي'));
      await tester.pumpAndSettle();
      expect(requested, [1, 2, 2]);
      expect(find.text('Page 2 item 0'), findsOneWidget);
      expect(tester.getTopLeft(find.text('Page 2 item 0')).dy, lessThan(200));
      await tester.scrollUntilVisible(find.text('السابق'), 400);
      expect(find.text('2 / 2'), findsOneWidget);
      await tester.tap(find.text('السابق'));
      await tester.pumpAndSettle();
      expect(requested, [1, 2, 2, 1]);
      expect(find.text('Page 1 item 0'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
