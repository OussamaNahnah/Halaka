import 'package:teacher_flutter/api.dart';
import 'package:teacher_flutter/draft.dart';
import 'package:teacher_flutter/draft_store.dart';

/// UI-only tests avoid file IO in Flutter's fake clock. SQLite is tested separately.
class MemoryDraftStore extends DraftStore {
  final data = <String, Json>{};
  @override
  Future<Json?> load(String owner) async =>
      data[owner] == null ? null : copy(data[owner]!);
  @override
  Future<void> save(String owner, Json snapshot) async {
    data[owner] = copy(snapshot);
  }

  @override
  Future<void> remove(String owner) async {
    data.remove(owner);
  }

  @override
  Future<void> flush() async {}
}
