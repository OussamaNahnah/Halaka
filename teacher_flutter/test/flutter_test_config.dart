import 'dart:async';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  sqfliteFfiInit();
  databaseFactory = databaseFactoryFfiNoIsolate;
  final directory = await Directory.systemTemp.createTemp(
    'halaka-sqlite-tests-',
  );
  await databaseFactory.setDatabasesPath(directory.path);
  setUp(() async {
    await deleteDatabase('${directory.path}/halaka_teacher_work.db');
  });
  tearDownAll(() async {
    await directory.delete(recursive: true);
  });
  await testMain();
}
