import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import 'api.dart';

/// Active school work only; never credentials.
class DraftStore {
  final String? path;
  Future<void> _tail = Future.value();
  DraftStore({this.path});

  Future<T> _withDatabase<T>(Future<T> Function(Database db) action) async {
    final location =
        path ?? '${await getDatabasesPath()}/halaka_teacher_work.db';
    final db = await openDatabase(
      location,
      version: 3,
      onCreate: (db, version) async {
        await db.execute(
          'CREATE TABLE active_work (owner TEXT PRIMARY KEY, snapshot TEXT NOT NULL)',
        );
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        await db.execute('DROP TABLE IF EXISTS teacher_settings');
      },
    );
    try {
      return await action(db);
    } finally {
      await db.close();
    }
  }

  Future<T> _serialize<T>(Future<T> Function() action) {
    final operation = _tail.then((_) => action());
    _tail = operation.then<void>((_) {}, onError: (Object e, StackTrace s) {});
    return operation;
  }

  Future<Json?> load(String owner) => _serialize(
    () => _withDatabase((db) async {
      final records = await db.query(
        'active_work',
        columns: ['snapshot'],
        where: 'owner = ?',
        whereArgs: [owner],
      );
      if (records.isEmpty) return null;
      return Map<String, dynamic>.from(
        jsonDecode(records.single['snapshot'] as String),
      );
    }),
  );
  Future<void> save(String owner, Json snapshot) {
    // Capture now: later mutations must not change queued database writes.
    final encoded = jsonEncode(snapshot);
    return _serialize(
      () => _withDatabase((db) async {
        await db.insert('active_work', {
          'owner': owner,
          'snapshot': encoded,
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }),
    );
  }

  Future<void> remove(String owner) => _serialize(
    () => _withDatabase((db) async {
      await db.delete('active_work', where: 'owner = ?', whereArgs: [owner]);
    }),
  );
  Future<void> flush() => _tail;
}
