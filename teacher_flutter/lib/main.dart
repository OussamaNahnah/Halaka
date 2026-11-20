import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'draft.dart';
import 'draft_store.dart';
import 'ui.dart';
import 'worksheet.dart';
import 'session_editor.dart';
import 'session_summary.dart';

void main() => runApp(const HalakaApp());

class HalakaApp extends StatelessWidget {
  const HalakaApp({super.key});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<ThemeMode>(
    valueListenable: appTheme,
    builder: (context, mode, child) => MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'حلقة',
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: halakaTheme(Brightness.light),
      darkTheme: halakaTheme(Brightness.dark),
      themeMode: mode,
      home: const Home(),
    ),
  );
}

class Home extends StatefulWidget {
  final TeacherApi? api;
  final DraftStore? draftStore;
  const Home({super.key, this.api, this.draftStore});
  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> {
  late final api = widget.api ?? TeacherApi();
  late final workStore = widget.draftStore ?? DraftStore();
  String? storageError;
  int saveGeneration = 0;
  bool saving = false;
  bool storageReady = false;
  String get workOwner => '${TeacherApi.base}|${api.profile!['id']}';
  final email = TextEditingController(), password = TextEditingController();
  List<Json> sessions = [], groups = [], surahs = [];
  final historyScroll = ScrollController();
  SessionDraft? draft;
  bool busy = true,
      uploading = false,
      showRoster = false,
      passwordVisible = false;
  String? error;
  int tab = 0, page = 1, lastPage = 1;
  Timer? retry;
  @override
  void initState() {
    super.initState();
    api.onUnauthorized = returnToLogin;
    start();
  }

  void returnToLogin() {
    retry?.cancel();
    if (!mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
    setState(() {
      draft = null;
      sessions = [];
      page = lastPage = 1;
      groups = [];
      surahs = [];
      showRoster = false;
      tab = 0;
      uploading = false;
      storageReady = false;
      password.clear();
      storageError = null;
      error = 'تم إنهاء جلسة الدخول. سجّل الدخول مجددًا.';
    });
  }

  Future<bool> persistWork() async {
    final d = draft;
    if (d == null || api.profile == null) return false;
    final generation = ++saveGeneration;
    if (mounted) setState(() => saving = true);
    try {
      await workStore.save(workOwner, {
        'draft': d.toJson(),
        'groups': groups,
        'surahs': surahs,
      });
      if (mounted && generation == saveGeneration) {
        setState(() {
          saving = false;
          storageError = null;
        });
      }
      return true;
    } catch (_) {
      if (mounted) {
        setState(() {
          saving = false;
          storageError =
              'تعذر الحفظ على الجهاز. أبقِ التطبيق مفتوحًا وحاول مجددًا.';
        });
      }
      return false;
    }
  }

  Future<void> restoreWork() async {
    Json? saved;
    try {
      saved = await workStore.load(workOwner);
    } catch (_) {
      storageError = 'تعذر فتح الحصة المحفوظة. حاول مجددًا قبل بدء حصة أخرى.';
      rethrow;
    }
    if (saved != null) {
      draft = SessionDraft.fromJson(Map<String, dynamic>.from(saved['draft']));
      groups = rows(saved['groups']);
      surahs = rows(saved['surahs']);
    }
    storageReady = true;
  }

  void retryRestoredWork() {
    if (draft?.locked == true && draft?.conflict != true) {
      retry?.cancel();
      retry = Timer(const Duration(seconds: 1), () {
        if (mounted) send();
      });
    }
  }

  Future<void> start() async {
    await api.restore();
    if (!mounted) return;
    if (api.token != null) {
      await run(() async {
        await restoreWork();
        retryRestoredWork();
        final result = await api.request('GET', '/profile');
        api.profile = Map<String, dynamic>.from(result['data']);
        await load();
      });
    } else {
      setState(() => busy = false);
    }
  }

  @override
  void dispose() {
    retry?.cancel();
    historyScroll.dispose();
    email.dispose();
    password.dispose();
    api.onUnauthorized = null;
    api.client.close();
    super.dispose();
  }

  Future<void> run(Future<void> Function() action) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action();
    } catch (e) {
      if (mounted && !(e is ApiError && e.status == 401 && api.token == null)) {
        error = '$e';
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> load({int? targetPage}) async {
    final requestedPage = targetPage ?? page;
    final result = await api.request('GET', '/sessions?page=$requestedPage');
    final totalPages = (result['meta']?['last_page'] as num?)?.toInt() ?? 1;
    if (requestedPage > totalPages && totalPages > 0) {
      await load(targetPage: totalPages);
      return;
    }
    if (!mounted || api.token == null) return;
    sessions = rows(result['data']);
    lastPage = totalPages < 1 ? 1 : totalPages;
    page = (result['meta']?['current_page'] as num?)?.toInt() ?? requestedPage;
  }

  Future<void> changePage(int targetPage) async {
    if (busy || targetPage < 1 || targetPage > lastPage) return;
    await run(() async {
      await load(targetPage: targetPage);
      if (historyScroll.hasClients) historyScroll.jumpTo(0);
    });
  }

  Future<bool> confirm(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('تأكيد'),
            ),
          ],
        ),
      ) ??
      false;
  Future<void> openSessionSummary(Json session) async {
    final edit = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => SessionSummary(
          api: api,
          sessionId: session['id'],
          canEdit: draft == null && storageReady,
        ),
      ),
    );
    if (edit == true && mounted) await prepare(session: session);
  }

  Future<void> prepare({Json? session}) async {
    if (!storageReady) return;
    if (draft != null) {
      setState(() => showRoster = true);
      return;
    }
    await run(() async {
      groups = rows((await api.request('GET', '/groups'))['data']);
      surahs = rows((await api.request('GET', '/quran/surahs'))['data']);
    });
    if (!mounted || error != null) return;
    int? group =
        session?['group']?['id'] ??
        groups.where((g) => g['suggested'] == true).firstOrNull?['id'] ??
        groups.firstOrNull?['id'];
    var date = DateTime.now();
    if (session == null) {
      final selected = await showSessionEditor(
        context,
        groups: groups,
        groupId: group,
      );
      if (selected == null || !mounted) return;
      group = selected['group_id'];
      date = DateTime.parse(selected['timestamp']);
    }
    await run(() async {
      for (var attempt = 0; attempt < 3; attempt++) {
        final source = session == null
            ? await api.request('GET', '/groups/$group/students')
            : await api.request('GET', '/sessions/${session['id']}');
        final revision = source['meta']['sync_revision'];
        final data = session == null
            ? null
            : Map<String, dynamic>.from(source['data']);
        final students = session == null
            ? rows(source['data'])
                  .map(
                    (s) => <String, dynamic>{
                      'student_id': s['id'],
                      'name': s['name'],
                      'evaluated_at': null,
                      'assessments': <Json>[],
                    },
                  )
                  .toList()
            : rows(data!['students']);
        final assignments = <int, List<Json>>{};
        bool consistent = true;
        for (final s in students) {
          s['assignments'] = <Json>[];
          final a = await api.request(
            'GET',
            '/students/${s['student_id']}/assignments',
          );
          if (a['meta']['sync_revision'] != revision) {
            consistent = false;
            break;
          }
          assignments[s['student_id']] = rows(a['data']);
          if (data != null) {
            s['assignments'] = rows(data['created_assignments'])
                .where(
                  (assignment) =>
                      assignment['student_id'] == s['student_id'] &&
                      assignment['archived'] != true,
                )
                .map(
                  (assignment) => <String, dynamic>{
                    ...assignment,
                    'assessments':
                        assignments[s['student_id']]!
                            .where(
                              (existing) => existing['id'] == assignment['id'],
                            )
                            .firstOrNull?['assessments'] ??
                        [],
                  },
                )
                .toList();
          }
        }
        if (!consistent) continue;
        showRoster = true;
        draft = SessionDraft(
          id: data?['id'],
          payload: {
            'client_uuid': data?['client_uuid'] ?? uuid.v4(),
            'base_sync_revision': revision,
            if (data != null) 'base_revision': data['revision'],
            'group_id': data == null ? group : data['group']?['id'],
            'timestamp': data?['timestamp'] ?? date.toUtc().toIso8601String(),
          },
          students: students,
          assignments: assignments,
        );
        await persistWork();
        return;
      }
      throw ApiError(409, 'تغيرت البيانات أثناء التحميل. حاول مجدداً.');
    });
  }

  Future<void> send() async {
    final d = draft;
    if (d == null || uploading || d.conflict) return;
    if (!d.locked) {
      for (final s in d.students.where((s) => s['evaluated_at'] != null)) {
        final problem = d.validateStudent(s);
        if (problem != null) {
          setState(() => error = '${s['name']}: $problem');
          return;
        }
      }
      final absent = d.students
          .where((s) => s['evaluated_at'] == null)
          .map((s) => s['name'])
          .join('، ');
      if (!await confirm(
        'إنهاء وإرسال الحصة',
        absent.isEmpty
            ? 'إرسال الحصة إلى الخادم؟'
            : 'سيُسجّل هؤلاء غائبين وتُستبعد مسوداتهم:\n$absent',
      )) {
        return;
      }
      d.freeze();
    }
    setState(() {
      uploading = true;
      error = null;
    });
    try {
      if (!await persistWork()) return;
      final acknowledgement = await api.request(
        d.id == null ? 'POST' : 'PUT',
        d.id == null ? '/sessions' : '/sessions/${d.id}',
        d.pending,
      );
      final accepted = acknowledgement['data'];
      if (acknowledgement['submission_id'] != d.pending!['submission_id'] ||
          accepted is! Map ||
          accepted['id'] is! int ||
          accepted['client_uuid'] != d.payload['client_uuid'] ||
          (d.id != null && accepted['id'] != d.id)) {
        throw ApiError(
          502,
          'لم يصل تأكيد حفظ الحصة. سنعيد المحاولة دون تكرارها.',
        );
      }
      await workStore.remove(workOwner);
      retry?.cancel();
      draft = null;
      showRoster = false;
      await load(targetPage: 1);
    } catch (e) {
      if (draft != d) return;
      error = '$e';
      if (e is ApiError && e.status == 409) d.conflict = true;
      if (e is ApiError && e.status == 422) d.pending = null;
      if (draft == d) await persistWork();
      if (e is! ApiError || e.status >= 500 || e.status == 429) {
        retry?.cancel();
        retry = Timer(const Duration(seconds: 30), () {
          if (mounted && draft == d) send();
        });
      }
    } finally {
      if (mounted) setState(() => uploading = false);
    }
  }

  Future<void> worksheet(Json student) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => Worksheet(
          student: student,
          draft: draft!,
          surahs: surahs,
          editSession: editMetadata,
          onChanged: persistWork,
        ),
      ),
    );
    if (mounted) setState(() {});
  }

  Widget login() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SizedBox(height: 40),
      const Center(child: HalakaLogo()),
      const SizedBox(height: 20),
      const Text(
        'تسجيل دخول الأستاذ',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
      ),
      const SizedBox(height: 8),
      const Text('أدخل حسابك للمتابعة', textAlign: TextAlign.center),
      const SizedBox(height: 24),
      Panel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: email,
              decoration: const InputDecoration(labelText: 'البريد الإلكتروني'),
              keyboardType: TextInputType.emailAddress,
              textDirection: TextDirection.ltr,
              autofillHints: const [AutofillHints.username],
            ),
            const SizedBox(height: 16),
            TextField(
              controller: password,
              decoration: InputDecoration(
                labelText: 'كلمة المرور',
                suffixIcon: IconButton(
                  tooltip: passwordVisible
                      ? 'إخفاء كلمة المرور'
                      : 'إظهار كلمة المرور',
                  onPressed: () =>
                      setState(() => passwordVisible = !passwordVisible),
                  icon: Icon(
                    passwordVisible
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                  ),
                ),
              ),
              obscureText: !passwordVisible,
              enableSuggestions: false,
              autocorrect: false,
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: busy
                  ? null
                  : () => run(() async {
                      try {
                        await api.login(email.text.trim(), password.text);
                        await restoreWork();
                        retryRestoredWork();
                        await load(targetPage: 1);
                      } finally {
                        password.clear();
                      }
                    }),
              child: Text(busy ? 'جارٍ الدخول…' : 'تسجيل الدخول'),
            ),
          ],
        ),
      ),
    ],
  );

  Future<void> changePassword() async {
    final current = TextEditingController(),
        next = TextEditingController(),
        confirmation = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تغيير كلمة المرور'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (final entry in [
                (current, 'كلمة المرور الحالية'),
                (next, 'كلمة المرور الجديدة'),
                (confirmation, 'تأكيد كلمة المرور'),
              ])
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: TextField(
                    controller: entry.$1,
                    obscureText: true,
                    decoration: InputDecoration(labelText: entry.$2),
                  ),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await run(() async {
        await api.request('PUT', '/profile/password', {
          'current_password': current.text,
          'password': next.text,
          'password_confirmation': confirmation.text,
        });
        await api.forget();
        sessions = [];
      });
    }
    current.dispose();
    next.dispose();
    confirmation.dispose();
  }

  Future<void> review() async {
    await run(() async {
      final d = draft!;
      final result = d.id == null
          ? await api.request(
              'GET',
              '/groups/${d.payload['group_id']}/students',
            )
          : await api.request('GET', '/sessions/${d.id}');
      final students = d.id == null
          ? rows(result['data'])
          : rows(result['data']['students']);
      final lines = <String>[];
      for (final s in students) {
        lines.add('${s['name']} ${s['evaluated_at'] ?? ''}');
        final data = await api.request(
          'GET',
          '/students/${s['student_id'] ?? s['id']}/assignments',
        );
        for (final a in rows(data['data'])) {
          lines.add(
            '${a['surah_name']} ${a['from_ayah']}–${a['to_ayah']} · ${a['is_passed'] == true ? 'ناجح' : 'قيد الإعداد'}',
          );
        }
        for (final a in rows(s['assessments'])) {
          lines.add(
            'تقييم ${a['quran_assignment_id']}: ${a['grade']} ${a['comment'] ?? ''}',
          );
        }
      }
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('نسخة الخادم الحالية'),
          content: SingleChildScrollView(
            child: SelectableText(lines.join('\n')),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c),
              child: const Text('إغلاق'),
            ),
          ],
        ),
      );
    });
  }

  Future<void> editMetadata() async {
    final d = draft;
    if (d == null || d.locked || busy || uploading) return;
    final selected = await showSessionEditor(
      context,
      groups: groups,
      groupId: d.payload['group_id'],
      timestamp: d.payload['timestamp'],
      editing: true,
      allowGroupChange: d.id == null,
    );
    if (selected == null || !mounted || draft != d || d.locked) return;
    if (selected['group_id'] == d.payload['group_id']) {
      setState(() => d.payload.addAll(selected));
      await persistWork();
      return;
    }
    final hasEvaluatedStudent = d.students.any(
      (student) => student['evaluated_at'] != null,
    );
    if (hasEvaluatedStudent &&
        !await confirm(
          'تغيير المجموعة',
          'سيتم تحميل طلاب المجموعة الجديدة وإلغاء التقييمات والملاحظات والواجبات المدخلة في هذه الحصة. هل تريد المتابعة؟',
        )) {
      return;
    }
    if (!mounted || draft != d) return;
    await run(() async {
      for (var attempt = 0; attempt < 3; attempt++) {
        final response = await api.request(
          'GET',
          '/groups/${selected['group_id']}/students',
        );
        final revision = response['meta']['sync_revision'];
        final students = rows(response['data'])
            .map(
              (student) => <String, dynamic>{
                'student_id': student['id'],
                'name': student['name'],
                'evaluated_at': null,
                'assessments': <Json>[],
                'assignments': <Json>[],
              },
            )
            .toList();
        final assignments = <int, List<Json>>{};
        var consistent = true;
        for (final student in students) {
          final response = await api.request(
            'GET',
            '/students/${student['student_id']}/assignments',
          );
          if (response['meta']['sync_revision'] != revision) {
            consistent = false;
            break;
          }
          assignments[student['student_id']] = rows(response['data']);
        }
        if (!consistent) continue;
        if (!mounted || draft != d || d.locked) return;
        draft = SessionDraft(
          payload: {
            ...selected,
            'client_uuid': uuid.v4(),
            'base_sync_revision': revision,
          },
          students: students,
          assignments: assignments,
        );
        showRoster = true;
        Navigator.of(context).popUntil((route) => route.isFirst);
        await persistWork();
        return;
      }
      throw ApiError(409, 'تغيرت البيانات أثناء التحميل. حاول مجدداً.');
    });
  }

  String groupName(SessionDraft d) =>
      groups
          .where((g) => g['id'] == d.payload['group_id'])
          .firstOrNull?['name'] ??
      'الحصة';
  Widget sessionAction() {
    final d = draft!;
    return SizedBox(
      width: double.infinity,
      child: FilledButton.icon(
        onPressed: busy || uploading
            ? null
            : d.conflict
            ? review
            : send,
        icon: uploadIcon(d.conflict ? Icons.compare_arrows : Icons.check),
        label: Text(
          uploading
              ? 'جارٍ الإرسال…'
              : d.conflict
              ? 'مراجعة الحصة قبل الإرسال'
              : d.locked
              ? 'إعادة محاولة الرفع'
              : d.id == null
              ? 'إنهاء الحصة وإرسالها'
              : 'إنهاء وإرسال التعديلات',
        ),
      ),
    );
  }

  Widget profile() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Panel(
        child: Center(
          child: Column(
            children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: forest.withValues(alpha: .12),
                child: Text(
                  (api.profile?['name'] as String? ?? '')
                          .characters
                          .firstOrNull ??
                      '',
                  style: const TextStyle(fontSize: 28),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                api.profile?['name'] ?? '',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                api.profile?['email'] ?? '',
                textDirection: TextDirection.ltr,
              ),
            ],
          ),
        ),
      ),
      Panel(
        child: SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('الوضع الداكن'),
          value: appTheme.value == ThemeMode.dark,
          onChanged: (v) =>
              appTheme.value = v ? ThemeMode.dark : ThemeMode.light,
        ),
      ),
      Panel(
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('تغيير كلمة المرور'),
          trailing: const Icon(Icons.chevron_left),
          onTap: busy || draft != null ? null : changePassword,
        ),
      ),
      const Text('تُحفظ الحصة غير المرسلة على هذا الجهاز حتى ينجح رفعها.'),
      const SizedBox(height: 16),
      OutlinedButton.icon(
        onPressed: busy || draft != null
            ? null
            : () async {
                if (await confirm('تسجيل الخروج', 'هل تريد تسجيل الخروج؟')) {
                  await run(() async {
                    await api.logout();
                    sessions = [];
                    groups = [];
                    surahs = [];
                    appTheme.value = ThemeMode.light;
                  });
                }
              },
        icon: const Icon(Icons.logout, size: 18),
        label: const Text('تسجيل الخروج'),
      ),
      if (draft != null)
        const Text(
          'أرسل الحصة وانتظر تأكيد حفظها قبل الخروج أو تغيير كلمة المرور.',
        ),
    ],
  );
  List<Widget> rosterContents() {
    final d = draft!;
    final completed = d.students.where((s) => s['evaluated_at'] != null).length;
    return [
      Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'الطلاب',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          Text(
            'تم تسجيل $completed من ${d.students.length}',
            style: const TextStyle(fontSize: 13),
          ),
        ],
      ),
      const SizedBox(height: 14),
      if (d.locked)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: StatusPill(
            d.conflict
                ? 'تعارض يحتاج مراجعة'
                : uploading
                ? 'جارٍ الإرسال'
                : 'بانتظار الإرسال',
            pending: true,
          ),
        ),
      for (var i = 0; i < d.students.length; i++)
        Builder(
          builder: (context) {
            final s = d.students[i];
            final done = s['evaluated_at'] != null;
            return Card(
              color: done
                  ? null
                  : Theme.of(context).brightness == Brightness.dark
                  ? const Color(0xff342e20)
                  : const Color(0xfffcf8ed),
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 6,
                ),
                leading: Container(
                  width: 42,
                  height: 42,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: (done ? forest : gold).withValues(alpha: .14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${i + 1}',
                    style: TextStyle(
                      color: done ? forest : const Color(0xff795c1e),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                title: Text(
                  s['name'],
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  done
                      ? 'تم التسجيل ✓'
                      : s['attendance_status'] == 'absent'
                      ? 'غائب'
                      : 'لم يُقيَّم بعد',
                  style: TextStyle(
                    color: Theme.of(context).brightness == Brightness.dark
                        ? null
                        : done
                        ? forest
                        : const Color(0xff795c1e),
                    fontSize: 12,
                  ),
                ),
                trailing: const Icon(Icons.chevron_left, size: 20),
                onTap: uploading ? null : () => worksheet(s),
              ),
            );
          },
        ),
      const SizedBox(height: 16),
      const Text(
        'تُحفظ الحصة على الجهاز حتى يؤكد الخادم استلامها.',
        style: TextStyle(fontSize: 12),
      ),
      if (d.conflict)
        const Text(
          'راجع نسخة الخادم وعملك المحلي قبل إلغاء التغييرات وإعادة إدخال التصحيحات.',
        ),
      if (d.conflict)
        TextButton.icon(
          onPressed: busy || uploading ? null : deleteUnsentSession,
          icon: const Icon(Icons.delete_outline, size: 18),
          label: const Text('حذف الحصة من الجهاز'),
          style: TextButton.styleFrom(
            foregroundColor: Theme.of(context).colorScheme.error,
          ),
        ),
    ];
  }

  String get currentSessionStatus => uploading
      ? 'جارٍ الرفع…'
      : draft!.conflict
      ? 'تعارض يحتاج مراجعة'
      : draft!.locked
      ? 'بانتظار الرفع'
      : 'حصة جارية';
  Widget uploadIcon(IconData fallback) => uploading
      ? const SizedBox(
          width: 18,
          height: 18,
          child: CircularProgressIndicator(strokeWidth: 2),
        )
      : Icon(fallback, size: 20);
  Future<void> deleteUnsentSession() async {
    final d = draft;
    if (d == null || (d.locked && !d.conflict) || uploading || busy) return;
    final label = d.conflict
        ? 'حذف الحصة من الجهاز'
        : d.id == null
        ? 'حذف الحصة غير المرسلة'
        : 'إلغاء التغييرات غير المرسلة';
    if (!await confirm(
      label,
      d.conflict
          ? 'ستُحذف النسخة المحلية التي بها تعارض. تبقى بيانات الخادم كما هي. هل تريد المتابعة؟'
          : d.id == null
          ? 'سيُحذف عمل هذه الحصة من الجهاز. هل تريد المتابعة؟'
          : 'ستُحذف التغييرات المحلية فقط. تبقى الحصة المحفوظة على الخادم كما هي.',
    )) {
      return;
    }
    if (draft != d || (d.locked && !d.conflict) || uploading) return;
    await run(() async {
      await workStore.remove(workOwner);
      retry?.cancel();
      draft = null;
      showRoster = false;
      storageError = null;
      saving = false;
    });
  }

  Widget activeSessionCard() {
    final d = draft!;
    final completed = d.students
        .where((student) => student['evaluated_at'] != null)
        .length;
    final muted = Theme.of(context).textTheme.bodySmall;
    return Card(
      child: InkWell(
        key: const ValueKey('active-session-card'),
        borderRadius: BorderRadius.circular(14),
        onTap: busy ? null : () => setState(() => showRoster = true),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary
                          .withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.menu_book_outlined,
                      size: 21,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          groupName(d),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          runSpacing: 4,
                          children: [
                            Text(
                              dateLabel(context, d.payload['timestamp']),
                              style: muted,
                            ),
                            Text(
                              timeLabel(context, d.payload['timestamp']),
                              style: muted,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  if ((!d.locked || d.conflict) && !uploading)
                    TextButton.icon(
                      onPressed: busy ? null : deleteUnsentSession,
                      style: TextButton.styleFrom(
                        foregroundColor: Theme.of(context).colorScheme.error,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        minimumSize: const Size(48, 48),
                      ),
                      icon: const Icon(Icons.delete_outline, size: 18),
                      label: const Text('حذف'),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 12,
                runSpacing: 8,
                children: [
                  StatusPill(currentSessionStatus, pending: true),
                  Text(
                    'تم تقييم $completed من ${d.students.length}',
                    style: muted,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: d.students.isEmpty ? 0 : completed / d.students.length,
                  minHeight: 4,
                ),
              ),
              if (d.locked)
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Text(
                    d.conflict
                        ? 'راجع التعارض لمتابعة إرسال الحصة.'
                        : 'بانتظار تأكيد الحفظ من الخادم.',
                    style: muted,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> history() => [
    if (draft != null) activeSessionCard(),
    if (sessions.isEmpty && !busy && draft == null)
      const Panel(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            children: [
              Icon(Icons.menu_book_outlined, size: 40, color: gold),
              SizedBox(height: 12),
              Text('لا توجد حصص مسجلة'),
              Text('ابدأ حصة جديدة لمتابعة طلابك.'),
            ],
          ),
        ),
      ),
    for (final s in sessions)
      Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: busy ? null : () => openSessionSummary(s),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              s['group']?['name'] ?? 'حصة',
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          Text(
                            dateLabel(context, s['timestamp']),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        spacing: 10,
                        runSpacing: 6,
                        children: [
                          Text(
                            '${timeLabel(context, s['timestamp'])}${s['students'] is List ? ' · ${s['students'].length} طلاب' : ''}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          StatusPill(
                            s['status'] == 'completed'
                                ? 'مكتملة · تم الإرسال'
                                : 'قيد الإنجاز · محفوظة',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                const Icon(Icons.chevron_left, size: 18),
              ],
            ),
          ),
        ),
      ),
    const SizedBox(height: 12),
    Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        TextButton(
          onPressed: busy || page <= 1 ? null : () => changePage(page - 1),
          child: const Text('السابق'),
        ),
        Text('$page / $lastPage'),
        TextButton(
          onPressed: busy || page >= lastPage
              ? null
              : () => changePage(page + 1),
          child: const Text('التالي'),
        ),
      ],
    ),
  ];
  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !showRoster && !saving && storageError == null,
    onPopInvokedWithResult: (didPop, result) {
      if (!didPop) {
        if (showRoster) {
          setState(() => showRoster = false);
        } else {
          setState(
            () => error = 'الحصة محفوظة على الجهاز. يمكنك متابعتها لاحقًا.',
          );
        }
      }
    },
    child: Scaffold(
      appBar: api.token == null
          ? null
          : AppBar(
              leading: showRoster && tab == 0
                  ? IconButton(
                      tooltip: 'الحصص',
                      onPressed: () => setState(() => showRoster = false),
                      icon: const Icon(Icons.arrow_back),
                    )
                  : null,
              title: api.token == null
                  ? const PageIdentity('حلقة', subtitle: 'متابعة أقرب لكل طالب')
                  : tab == 1
                  ? const PageIdentity('حسابي')
                  : showRoster && draft != null
                  ? PageIdentity(
                      groupName(draft!),
                      subtitle:
                          '${dateLabel(context, draft!.payload['timestamp'])} · ${timeLabel(context, draft!.payload['timestamp'])}',
                    )
                  : PageIdentity(
                      'الحصص',
                      subtitle: 'أهلًا ${api.profile?['name'] ?? ''}',
                    ),
              actions: [
                if (api.token != null &&
                    tab == 0 &&
                    showRoster &&
                    draft != null)
                  IconButton(
                    tooltip: 'تعديل الحصة',
                    onPressed: busy || draft!.locked ? null : editMetadata,
                    icon: const Icon(Icons.edit_outlined, size: 22),
                  ),
                if (api.token != null &&
                    tab == 0 &&
                    showRoster &&
                    draft != null &&
                    (!draft!.locked || draft!.conflict))
                  PopupMenuButton<String>(
                    tooltip: 'خيارات الحصة',
                    enabled: !busy && !uploading,
                    onSelected: (_) => deleteUnsentSession(),
                    itemBuilder: (context) => [
                      PopupMenuItem(
                        value: 'delete-local',
                        child: Row(
                          children: [
                            Icon(
                              Icons.delete_outline,
                              size: 20,
                              color: Theme.of(context).colorScheme.error,
                            ),
                            const SizedBox(width: 10),
                            Flexible(
                              child: Text(
                                draft!.conflict
                                    ? 'حذف الحصة من الجهاز'
                                    : draft!.id == null
                                    ? 'حذف الحصة غير المرسلة'
                                    : 'إلغاء التغييرات غير المرسلة',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                if (api.token != null && tab == 0 && !showRoster)
                  IconButton(
                    tooltip: 'تحديث الحصص',
                    onPressed: busy || uploading ? null : () => run(load),
                    icon: const Icon(Icons.refresh, size: 22),
                  ),
              ],
            ),
      bottomNavigationBar: api.token == null
          ? null
          : showRoster && tab == 0 && draft != null
          ? ActionDock(child: sessionAction())
          : ColoredBox(
              color: Theme.of(context).colorScheme.surface,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (tab == 0)
                    ActionDock(
                      safeBottom: false,
                      child: SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: busy || uploading || !storageReady
                              ? null
                              : draft == null
                              ? () => prepare()
                              : () => setState(() => showRoster = true),
                          icon: uploadIcon(
                            draft == null
                                ? Icons.add
                                : Icons.play_arrow_outlined,
                          ),
                          label: Text(
                            draft == null
                                ? 'إنشاء حصة'
                                : uploading
                                ? 'جارٍ الرفع…'
                                : draft!.conflict
                                ? 'مراجعة الحصة'
                                : draft!.locked
                                ? 'بانتظار الرفع · عرض الحصة'
                                : 'متابعة الحصة الحالية',
                          ),
                        ),
                      ),
                    ),
                  NavigationBar(
                    selectedIndex: tab,
                    onDestinationSelected: (v) => setState(() {
                      tab = v;
                      showRoster = false;
                    }),
                    destinations: const [
                      NavigationDestination(
                        icon: Icon(Icons.home_outlined),
                        label: 'الحصص',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.person_outline),
                        label: 'حسابي',
                      ),
                    ],
                  ),
                ],
              ),
            ),
      body: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1100),
            child: Column(
              children: [
                if (busy || uploading)
                  const LinearProgressIndicator(minHeight: 2),
                if (api.token != null) const Divider(height: 1),
                Expanded(
                  child: ListView(
                    controller: historyScroll,
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (storageError != null)
                        Panel(
                          child: Column(
                            children: [
                              Text(
                                storageError!,
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.error,
                                ),
                              ),
                              TextButton(
                                onPressed: () {
                                  if (draft == null) {
                                    run(restoreWork);
                                  } else {
                                    persistWork();
                                  }
                                },
                                child: const Text('إعادة محاولة الحفظ'),
                              ),
                            ],
                          ),
                        ),
                      if (error != null)
                        Panel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              SelectableText(
                                error!,
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.error,
                                ),
                              ),
                            ],
                          ),
                        ),
                      if (api.token == null)
                        Center(
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 500),
                            child: login(),
                          ),
                        )
                      else if (tab == 1)
                        Center(
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 600),
                            child: profile(),
                          ),
                        )
                      else if (showRoster && draft != null)
                        ...rosterContents()
                      else
                        ...history(),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
