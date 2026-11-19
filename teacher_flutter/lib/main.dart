import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'draft.dart';
import 'ui.dart';
import 'worksheet.dart';
import 'session_editor.dart';

void main() => runApp(const HalakaApp());

class HalakaApp extends StatelessWidget {
  const HalakaApp({super.key});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<ThemeMode>(
    valueListenable: appTheme,
    builder: (context, mode, child) => MaterialApp(
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
  const Home({super.key, this.api});
  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> {
  late final api = widget.api ?? TeacherApi();
  final email = TextEditingController(), password = TextEditingController();
  List<Json> sessions = [], groups = [], surahs = [];
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
    start();
  }

  Future<void> start() async {
    await api.restore();
    if (!mounted) return;
    if (api.token != null) {
      await run(() async {
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
    email.dispose();
    password.dispose();
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
      if (mounted) error = '$e';
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> load() async {
    final result = await api.request('GET', '/sessions?page=$page');
    sessions = rows(result['data']);
    lastPage = result['meta']?['last_page'] ?? 1;
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
  Future<void> prepare({Json? session}) async {
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
      await api.request(
        d.id == null ? 'POST' : 'PUT',
        d.id == null ? '/sessions' : '/sessions/${d.id}',
        d.pending,
      );
      retry?.cancel();
      draft = null;
      showRoster = false;
      await load();
    } catch (e) {
      error = '$e';
      if (e is ApiError && e.status == 409) d.conflict = true;
      if (e is ApiError && e.status == 422) d.pending = null;
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
        ),
      ),
    );
    if (mounted) setState(() {});
  }

  Widget login() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SizedBox(height: 24),
      const Text('أهلًا بك في حلقة', style: TextStyle(color: forest)),
      const Text(
        'متابعة أقرب،\nلكل طالب.',
        style: TextStyle(
          fontSize: 30,
          fontWeight: FontWeight.w800,
          height: 1.3,
        ),
      ),
      const Text('سجّل الدخول بحساب الأستاذ الذي أنشأته الإدارة.'),
      const SizedBox(height: 24),
      Panel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'تسجيل دخول الأستاذ',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 20),
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
                        await load();
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

  Future<void> reauthenticate() async {
    final secret = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تجديد تسجيل الدخول'),
        content: TextField(
          controller: secret,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'كلمة المرور'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('دخول'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await run(() async {
        await api.login(api.profile!['email'], secret.text);
      });
    }
    secret.dispose();
  }

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
    if (d == null || d.locked) return;
    final selected = await showSessionEditor(
      context,
      groups: groups,
      groupId: d.payload['group_id'],
      timestamp: d.payload['timestamp'],
      editing: true,
    );
    if (selected != null && mounted) setState(() => d.payload.addAll(selected));
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
        icon: Icon(d.conflict ? Icons.compare_arrows : Icons.check, size: 20),
        label: Text(
          uploading
              ? 'جارٍ الإرسال…'
              : d.conflict
              ? 'مراجعة الحصة قبل الإرسال'
              : d.locked
              ? 'إرسال الآن'
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
      const Text(
        'يبقى فقط الملف الشخصي ورمز الدخول محفوظين. أرسل الحصة قبل إغلاق التطبيق.',
      ),
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
          'أرسل الحصة أو تجاهل مسودتها قبل الخروج أو تغيير كلمة المرور.',
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
        'العمل مؤقت حتى تأكيد الإرسال. لا يبقى بعد إغلاق التطبيق.',
        style: TextStyle(fontSize: 12),
      ),
      if (d.conflict)
        const Text(
          'راجع نسخة الخادم وعملك المحلي قبل تجاهل المسودة وإعادة إدخال التصحيحات.',
        ),
      TextButton(
        onPressed: uploading
            ? null
            : () async {
                if (await confirm(
                  'تجاهل المسودة',
                  'سيُفقد العمل المؤقت. إذا بدأ الإرسال فقد تكون النسخة مقبولة على الخادم؛ حدّث الحصص للتحقق.',
                )) {
                  retry?.cancel();
                  setState(() {
                    draft = null;
                    showRoster = false;
                    error = null;
                  });
                }
              },
        child: const Text(
          'تجاهل المسودة',
          style: TextStyle(color: Color(0xffa52e35)),
        ),
      ),
    ];
  }

  List<Widget> history() => [
    if (sessions.isEmpty && !busy)
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
          onTap: busy || draft != null ? null : () => prepare(session: s),
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
          onPressed: busy || page <= 1
              ? null
              : () => run(() async {
                  page--;
                  await load();
                }),
          child: const Text('السابق'),
        ),
        Text('$page / $lastPage'),
        TextButton(
          onPressed: busy || page >= lastPage
              ? null
              : () => run(() async {
                  page++;
                  await load();
                }),
          child: const Text('التالي'),
        ),
      ],
    ),
  ];
  @override
  Widget build(BuildContext context) => PopScope(
    canPop: draft == null,
    onPopInvokedWithResult: (didPop, result) {
      if (!didPop) {
        if (showRoster) {
          setState(() => showRoster = false);
        } else {
          setState(() => error = 'أرسل الحصة قبل الإغلاق؛ العمل مؤقت.');
        }
      }
    },
    child: Scaffold(
      appBar: AppBar(
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
          if (api.token != null && tab == 0 && showRoster && draft != null)
            IconButton(
              tooltip: 'تعديل الحصة',
              onPressed: busy || draft!.locked ? null : editMetadata,
              icon: const Icon(Icons.edit_outlined, size: 22),
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
                          onPressed: busy || uploading
                              ? null
                              : draft == null
                              ? () => prepare()
                              : () => setState(() => showRoster = true),
                          icon: Icon(
                            draft == null
                                ? Icons.add
                                : Icons.play_arrow_outlined,
                            size: 20,
                          ),
                          label: Text(
                            draft == null
                                ? 'إنشاء حصة'
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
                const Divider(height: 1),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
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
                              if (api.token != null)
                                TextButton(
                                  onPressed: busy || uploading
                                      ? null
                                      : reauthenticate,
                                  child: const Text(
                                    'تجديد تسجيل الدخول عند الحاجة',
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
