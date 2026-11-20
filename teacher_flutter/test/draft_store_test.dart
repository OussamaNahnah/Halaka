import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_flutter/draft_store.dart';
import 'package:teacher_flutter/draft.dart';

void main() {
  test(
    'SQLite reopens unfinished evaluations and isolates accounts and API hosts',
    () async {
      final draft = SessionDraft(
        payload: {'client_uuid': uuid.v4(), 'base_sync_revision': 3},
        students: [
          {
            'student_id': 1,
            'name': 'أحمد',
            'evaluated_at': null,
            'assessments': [
              {
                'client_uuid': uuid.v4(),
                'quran_assignment_id': 7,
                'grade': 8.125,
                'comment': 'محاولة',
              },
            ],
            'assignments': [],
            '_composer': {
              'open': true,
              'surah': 'الفاتحة',
              'from': '1',
              'to': '5',
              'type': 'revision',
            },
          },
        ],
        assignments: {
          1: [
            {'id': 7, 'surah_name': 'الفاتحة'},
          ],
        },
      );
      final store = DraftStore();
      await store.save('host-a|1', {
        'draft': draft.toJson(),
        'groups': [],
        'surahs': [],
      });
      final reopened = DraftStore();
      final loaded = SessionDraft.fromJson(
        (await reopened.load('host-a|1'))!['draft'],
      );
      expect(loaded.students.single['assessments'][0]['grade'], 8.125);
      expect(loaded.students.single['_composer']['to'], '5');
      expect(loaded.students.single['evaluated_at'], isNull);
      expect(loaded.payload['base_sync_revision'], 3);
      expect(await reopened.load('host-a|2'), isNull);
      expect(await reopened.load('host-b|1'), isNull);
    },
  );
  test('queued payload survives reopening unchanged and clears only the selected owner', () async {
    final draft = SessionDraft(
      payload: {'client_uuid': uuid.v4(), 'base_sync_revision': 3},
      students: [],
      assignments: {},
    );
    draft.freeze();
    final store = DraftStore();
    await store.save('teacher-1', {'draft': draft.toJson()});
    await store.save('teacher-2', {'draft': draft.toJson()});
    final restored = SessionDraft.fromJson(
      (await DraftStore().load('teacher-1'))!['draft'],
    );
    expect(restored.locked, true);
    expect(restored.freeze(), draft.pending);
    await store.remove('teacher-1');
    expect(await DraftStore().load('teacher-1'), isNull);
    expect(await DraftStore().load('teacher-2'), isNotNull);
  });
  test('ordered snapshot writes preserve the latest edit', () async {
    final store = DraftStore();
    final data = <String, dynamic>{'grade': 1};
    final first = store.save('teacher', data);
    data['grade'] = 2;
    final second = store.save('teacher', data);
    data['grade'] = 3;
    await Future.wait([first, second]);
    expect((await DraftStore().load('teacher'))!['grade'], 2);
  });
}
