import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/main.dart';
import 'package:teacher_flutter/ui.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/worksheet.dart';

void main() {
  testWidgets(
    'teacher flow uses Laravel classroom reads and atomic submission',
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
      Json? submitted;
      final api = TeacherApi(
        client: MockClient((r) async {
          expect(r.headers['Authorization'], 'Bearer jwt');
          Json result;
          final path = r.url.path;
          if (path == '/api/profile') {
            result = {
              'data': {
                'id': 1,
                'name': 'Teacher',
                'email': 'teacher@example.com',
              },
            };
          } else if (path == '/api/sessions' && r.method == 'GET') {
            result = {
              'data': [],
              'meta': {'last_page': 1},
            };
          } else if (path == '/api/groups') {
            result = {
              'data': [
                {'id': 1, 'name': 'مجموعة النور', 'suggested': true},
              ],
            };
          } else if (path == '/api/quran/surahs') {
            result = {
              'data': [
                {'id': 1, 'name': 'الفاتحة', 'verses': 7},
              ],
            };
          } else if (path == '/api/groups/1/students') {
            result = {
              'data': [
                {'id': 10, 'name': 'أحمد'},
              ],
              'meta': {'sync_revision': 3},
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
          } else if (path == '/api/sessions' && r.method == 'POST') {
            submitted = jsonDecode(r.body);
            result = {
              'data': {},
              'meta': {'sync_revision': 4},
            };
          } else {
            fail('Unexpected ${r.method} $path');
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
          home: Home(api: api),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('إنشاء حصة'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('بدء'));
      await tester.pumpAndSettle();
      expect(find.text('لم يُقيَّم بعد'), findsOneWidget);
      await tester.tap(find.text('أحمد'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, '-3.125');
      await tester.pumpAndSettle();
      await tester.tap(find.text('ملاحظة'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).last, 'مراجعة');
      await tester.tap(find.text('إنهاء الطالب'));
      await tester.pumpAndSettle();
      expect(find.text('تم التسجيل ✓'), findsOneWidget);
      await tester.tap(find.text('إنهاء الحصة وإرسالها'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('تأكيد'));
      await tester.pumpAndSettle();
      expect(submitted!['base_sync_revision'], 3);
      expect(submitted!['students'][0]['assessments'][0]['grade'], -3.125);
      expect(
        submitted!['students'][0]['assessments'][0]['needs_repeat'],
        false,
      );
      expect(submitted!['students'][0]['evaluated_at'], isNotNull);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'inline assignment preserves grade and guards unfinished composer',
    (tester) async {
      final student = <String, dynamic>{
        'student_id': 1,
        'name': 'أحمد',
        'evaluated_at': null,
        'assignments': <Json>[],
        'assessments': <Json>[],
      };
      final draft = SessionDraft(
        payload: {},
        students: [
          student,
          {
            'student_id': 2,
            'name': 'علي',
            'evaluated_at': null,
            'assignments': <Json>[],
            'assessments': <Json>[],
          },
        ],
        assignments: {
          1: [
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
      );
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: const [Locale('ar')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          theme: halakaTheme(Brightness.light),
          home: Worksheet(
            student: student,
            draft: draft,
            surahs: const [
              {'name': 'الفاتحة'},
            ],
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, '9');
      await tester.ensureVisible(find.text('واجب جديد'));
      await tester.tap(find.text('واجب جديد'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'إلى الآية'), '5');
      await tester.ensureVisible(find.text('التالي ←'));
      await tester.tap(find.text('التالي ←'));
      await tester.pumpAndSettle();
      expect(find.text('الواجب لم يُضف بعد'), findsOneWidget);
      await tester.tap(find.text('متابعة الإدخال'));
      await tester.pumpAndSettle();
      expect(find.text('أحمد'), findsOneWidget);
      expect(student['assessments'][0]['grade'], 9);
      expect(student['evaluated_at'], isNull);
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [390.0, 1024.0]) {
    testWidgets('worksheet retains grades across navigation at width $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final students = [
        for (var i = 1; i <= 2; i++)
          <String, dynamic>{
            'student_id': i,
            'name': 'طالب $i',
            'evaluated_at': null,
            'assignments': <Json>[],
            'assessments': <Json>[],
          },
      ];
      final draft = SessionDraft(
        payload: {},
        students: students,
        assignments: {
          for (var i = 1; i <= 2; i++)
            i: [
              {
                'id': i,
                'surah_name': 'الفاتحة',
                'type': 'revision',
                'from_ayah': 1,
                'to_ayah': 7,
                'is_passed': false,
              },
            ],
        },
      );
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ar'),
          supportedLocales: const [Locale('ar')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          theme: halakaTheme(Brightness.light),
          home: Worksheet(
            student: students.first,
            draft: draft,
            surahs: const [
              {'name': 'الفاتحة'},
            ],
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, '8.125');
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('التالي ←'));
      await tester.tap(find.text('التالي ←'));
      await tester.pumpAndSettle();
      expect(find.text('طالب 2'), findsOneWidget);
      await tester.ensureVisible(find.text('→ السابق'));
      await tester.tap(find.text('→ السابق'));
      await tester.pumpAndSettle();
      expect(find.text('8.125'), findsOneWidget);
      expect(students.first['evaluated_at'], isNull);
      expect(tester.takeException(), isNull);
    });
  }
}
