import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/draft_store.dart';
import 'package:teacher_flutter/ui.dart';
import 'package:teacher_flutter/worksheet.dart';

void main() {
  test('SQLite upgrade preserves existing active work', () async {
    final path = '${await getDatabasesPath()}/repeat-upgrade.db';
    final old = await openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute(
          'CREATE TABLE active_work (owner TEXT PRIMARY KEY, snapshot TEXT NOT NULL)',
        );
      },
    );
    await old.insert('active_work', {
      'owner': 'host|1',
      'snapshot': '{"draft":"retained"}',
    });
    await old.close();
    final store = DraftStore(path: path);
    expect((await store.load('host|1'))!['draft'], 'retained');
    await store.save('host|1', {'draft': 'updated'});
    expect((await DraftStore(path: path).load('host|1'))!['draft'], 'updated');
  });

  testWidgets('repeat sets zero and locks grading until unchecked', (
    tester,
  ) async {
    final student = <String, dynamic>{
      'student_id': 1,
      'name': 'أحمد',
      'evaluated_at': null,
      'assignments': <Json>[],
      'assessments': <Json>[],
    };
    final draft = SessionDraft(
      payload: {},
      students: [student],
      assignments: {
        1: [
          for (final id in [7, 8])
            {
              'id': id,
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
        home: Worksheet(student: student, draft: draft, surahs: const []),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Checkbox).first);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    expect(student['assessments'][0]['grade'], 0);
    expect(
      tester
          .widget<TextFormField>(find.byKey(const ValueKey('grade-1-7')))
          .enabled,
      false,
    );
    expect(student['assessments'][0]['needs_repeat'], true);
    expect(find.text('0'), findsOneWidget);
    await tester.ensureVisible(find.byType(Checkbox).last);
    await tester.tap(find.byType(Checkbox).last);
    await tester.pumpAndSettle();
    expect(find.text('علامة الإعادة الافتراضية'), findsNothing);
    expect(student['assessments'][1]['grade'], 0);
    expect(find.text('0'), findsNWidgets(2));
    await tester.tap(find.byType(Checkbox).first);
    await tester.pumpAndSettle();
    expect(student['assessments'][0]['needs_repeat'], false);
    expect(student['assessments'][0]['grade'], 0);
    expect(
      tester
          .widget<TextFormField>(find.byKey(const ValueKey('grade-1-7')))
          .enabled,
      true,
    );
    await tester.enterText(find.byKey(const ValueKey('grade-1-7')), '8.5');
    await tester.pumpAndSettle();
    expect(student['assessments'][0]['grade'], 8.5);
    expect(tester.takeException(), isNull);
  });
}
