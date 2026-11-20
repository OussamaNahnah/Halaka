import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'draft.dart';
import 'ui.dart';

class Worksheet extends StatefulWidget {
  final Json student;
  final SessionDraft draft;
  final List<Json> surahs;
  final Future<void> Function()? editSession;
  final Future<bool> Function()? onChanged;
  const Worksheet({
    super.key,
    required this.student,
    required this.draft,
    required this.surahs,
    this.editSession,
    this.onChanged,
  });
  @override
  State<Worksheet> createState() => _WorksheetState();
}

class _WorksheetState extends State<Worksheet> {
  late int position;
  String? error;
  String? storageError;
  final gradeControllers = <String, TextEditingController>{};
  final visibleNotes = <String>{};
  final from = TextEditingController(text: '1'), to = TextEditingController();
  bool composer = false;
  String? surah;
  String type = 'new_memorization';
  Json get student => widget.draft.students[position];
  List<Json> get attempts => (student['assessments'] as List).cast<Json>();
  List<Json> get additions => (student['assignments'] as List).cast<Json>();
  bool get locked => widget.draft.locked || student['archived'] == true;
  @override
  void initState() {
    super.initState();
    position = widget.draft.students.indexOf(widget.student);
    restoreComposer();
    from.addListener(saveComposer);
    to.addListener(saveComposer);
  }

  @override
  void dispose() {
    for (final controller in gradeControllers.values) {
      controller.dispose();
    }
    from.dispose();
    to.dispose();
    super.dispose();
  }

  bool restoringComposer = false;
  void restoreComposer() {
    restoringComposer = true;
    final saved = student['_composer'] as Map?;
    composer = saved?['open'] == true;
    surah = saved?['surah'];
    type = saved?['type'] ?? 'new_memorization';
    from.text = saved?['from'] ?? '1';
    to.text = saved?['to'] ?? '';
    restoringComposer = false;
  }

  void saveComposer() {
    if (restoringComposer) return;
    student['_composer'] = {
      'open': composer,
      'surah': surah,
      'type': type,
      'from': from.text,
      'to': to.text,
    };
    if (widget.onChanged != null) {
      unawaited(
        widget.onChanged!().then((saved) {
          if (mounted) {
            setState(
              () => storageError = saved
                  ? null
                  : 'تعذر الحفظ على الجهاز. أبقِ التطبيق مفتوحًا وحاول مجددًا.',
            );
          }
        }),
      );
    }
  }

  void changed() {
    student['evaluated_at'] = null;
    // Capture after the synchronous add/remove/form reset has completed.
    scheduleMicrotask(() {
      if (mounted) saveComposer();
    });
  }

  Json? attemptFor(Json assignment) => attempts
      .where((a) => a['quran_assignment_id'] == assignment['id'])
      .firstOrNull;
  void edit(Json assignment, String key, dynamic value) {
    if (locked) return;
    setState(() {
      var a = attemptFor(assignment);
      if (a == null) {
        a = <String, dynamic>{
          'client_uuid': uuid.v4(),
          'quran_assignment_id': assignment['id'],
          'grade': '',
          'comment': '',
          'needs_repeat': false,
        };
        attempts.add(a);
      }
      a[key] = value;
      if ('${a['grade']}'.trim().isEmpty &&
          '${a['comment'] ?? ''}'.trim().isEmpty &&
          a['needs_repeat'] != true &&
          a['id'] == null) {
        attempts.remove(a);
      }
      changed();
    });
  }

  void toggleRepeat(Json assignment, bool repeat) {
    if (locked) return;
    if (repeat) {
      final key = '${student['student_id']}-${assignment['id']}';
      gradeControllers[key]?.text = '0';
      edit(assignment, 'grade', 0);
    }
    edit(assignment, 'needs_repeat', repeat);
  }

  Future<bool> discardComposer() async {
    if (!composer) return true;
    if (surah == null &&
        to.text.isEmpty &&
        from.text == '1' &&
        type == 'new_memorization') {
      setState(() => composer = false);
      saveComposer();
      return true;
    }
    final discard = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('الواجب لم يُضف بعد'),
        content: const Text('أضف الواجب أولاً أو تخلّ عنه للمتابعة.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('متابعة الإدخال'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('التخلّي عن الواجب'),
          ),
        ],
      ),
    );
    if (discard == true && mounted) {
      setState(() {
        composer = false;
        surah = null;
        from.text = '1';
        to.clear();
      });
    }
    if (discard == true) saveComposer();
    return discard == true;
  }

  void add() {
    final first = int.tryParse(from.text), last = int.tryParse(to.text);
    if (surah == null ||
        first == null ||
        last == null ||
        first <= 0 ||
        last < first) {
      setState(() => error = 'اختر سورة وأدخل أرقام آيات موجبة ومرتبة.');
      return;
    }
    setState(() {
      additions.add({
        'client_uuid': uuid.v4(),
        'surah_name': surah,
        'from_ayah': first,
        'to_ayah': last,
        'type': type,
        'is_passed': false,
      });
      changed();
      composer = false;
      surah = null;
      from.text = '1';
      to.clear();
      error = null;
    });
  }

  Future<void> step(int delta) async {
    if (!await discardComposer() || !mounted) return;
    setState(() {
      position += delta;
      restoreComposer();
      error = null;
    });
  }

  Future<void> finish() async {
    if (composer) {
      setState(() => error = 'أضف الواجب المفتوح أو تخلّ عنه أولاً.');
      return;
    }
    final problem = widget.draft.validateStudent(student);
    if (problem != null) {
      setState(() => error = problem);
      return;
    }
    student['evaluated_at'] = DateTime.now().toUtc().toIso8601String();
    if (widget.onChanged != null && !await widget.onChanged!()) {
      if (mounted) {
        setState(() => error = 'تعذر الحفظ على الجهاز. حاول مجددًا.');
      }
      return;
    }
    if (mounted) Navigator.pop(context);
  }

  Widget assessment(Json assignment) {
    final a = attemptFor(assignment);
    final key = '${student['student_id']}-${assignment['id']}';
    final disabled = locked || a?['assignment_archived'] == true;
    final history = rows(assignment['assessments']);
    return Container(
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
        ),
      ),
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${assignment['type'] == 'revision' ? 'مراجعة' : 'حفظ جديد'} · ${assignment['surah_name']}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        'الآيات ${assignment['from_ayah']}–${assignment['to_ayah']}${history.isEmpty ? '' : ' · السابق ${history.last['grade']}'}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  width: 76,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text(
                        'العلامة',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12),
                      ),
                      const SizedBox(height: 4),
                      Semantics(
                        label: 'العلامة',
                        child: TextFormField(
                          key: ValueKey('grade-$key'),
                          controller: gradeControllers.putIfAbsent(
                            key,
                            () => TextEditingController(
                              text: a?['grade']?.toString() ?? '',
                            ),
                          ),
                          enabled:
                              !disabled &&
                              !(a?['needs_repeat'] ??
                                  (a == null ? false : a['is_passed'] != true)),
                          decoration: const InputDecoration(hintText: '—'),
                          textAlign: TextAlign.center,
                          textDirection: TextDirection.ltr,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                            signed: true,
                          ),
                          onChanged: (v) =>
                              edit(assignment, 'grade', num.tryParse(v) ?? v),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Checkbox(
                        value:
                            a?['needs_repeat'] ??
                            (a == null ? false : a['is_passed'] != true),
                        onChanged: disabled
                            ? null
                            : (v) => toggleRepeat(assignment, v == true),
                      ),
                      const Flexible(
                        child: Text(
                          'يحتاج إعادة',
                          style: TextStyle(fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),
                TextButton.icon(
                  onPressed: () => setState(() {
                    if (!visibleNotes.add(key)) visibleNotes.remove(key);
                  }),
                  icon: Icon(
                    (a?['comment'] ?? '').toString().isEmpty
                        ? Icons.chat_bubble_outline
                        : Icons.chat_bubble,
                    size: 18,
                  ),
                  label: Text(
                    visibleNotes.contains(key) ? 'إخفاء الملاحظة' : 'ملاحظة',
                  ),
                ),
              ],
            ),
            if (visibleNotes.contains(key))
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: TextFormField(
                  key: ValueKey('note-$key'),
                  initialValue: a?['comment'] ?? '',
                  enabled: !disabled,
                  decoration: const InputDecoration(
                    labelText: 'ملاحظة اختيارية',
                  ),
                  minLines: 1,
                  maxLines: null,
                  onChanged: (v) => edit(assignment, 'comment', v),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget nextAssignments() => Panel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'الواجبات التالية',
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold),
              ),
            ),
            const Text('اختياري', style: TextStyle(fontSize: 12)),
            if (additions.isNotEmpty)
              Padding(
                padding: const EdgeInsetsDirectional.only(start: 8),
                child: StatusPill('${additions.length}'),
              ),
          ],
        ),
        const SizedBox(height: 12),
        for (final a in additions)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(
              '${a['type'] == 'revision' ? 'مراجعة' : 'حفظ جديد'} · ${a['surah_name']}',
            ),
            subtitle: Text('الآيات ${a['from_ayah']}–${a['to_ayah']}'),
            trailing: IconButton(
              tooltip: 'إزالة الواجب',
              onPressed:
                  locked ||
                      rows(a['assessments']).isNotEmpty ||
                      attempts.any(
                        (attempt) =>
                            attempt['quran_assignment_id'] != null &&
                            attempt['quran_assignment_id'] == a['id'],
                      )
                  ? null
                  : () => setState(() {
                      additions.remove(a);
                      if (a['id'] != null) {
                        student.putIfAbsent(
                          'removed_assignment_ids',
                          () => <int>[],
                        );
                        (student['removed_assignment_ids'] as List).add(
                          a['id'],
                        );
                      }
                      changed();
                    }),
              icon: const Icon(Icons.close, color: Color(0xffa52e35)),
            ),
          ),
        if (!composer)
          OutlinedButton.icon(
            onPressed: locked
                ? null
                : () {
                    setState(() => composer = true);
                    saveComposer();
                  },
            icon: const Icon(Icons.add),
            label: const Text('واجب جديد'),
          )
        else ...[
          Row(
            textDirection: TextDirection.rtl,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: surah,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'السورة'),
                  hint: const Text('اختر سورة'),
                  items: widget.surahs
                      .map(
                        (s) => DropdownMenuItem<String>(
                          value: s['name'],
                          child: Text(s['name']),
                        ),
                      )
                      .toList(),
                  onChanged: (v) {
                    setState(() => surah = v);
                    saveComposer();
                  },
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: type,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'النوع'),
                  items: const [
                    DropdownMenuItem(
                      value: 'new_memorization',
                      child: Text('حفظ جديد'),
                    ),
                    DropdownMenuItem(value: 'revision', child: Text('مراجعة')),
                  ],
                  onChanged: (v) {
                    setState(() => type = v!);
                    saveComposer();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: from,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'من الآية'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: to,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'إلى الآية'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              FilledButton(onPressed: add, child: const Text('إضافة')),
              const SizedBox(width: 10),
              OutlinedButton(
                onPressed: discardComposer,
                child: const Text('إلغاء'),
              ),
            ],
          ),
        ],
      ],
    ),
  );
  @override
  Widget build(BuildContext context) {
    final assignments = widget.draft.assignments[student['student_id']] ?? [];
    final visible = assignments
        .where(
          (a) =>
              a['is_passed'] != true ||
              attempts.any((t) => t['quran_assignment_id'] == a['id']),
        )
        .toList();
    final canFinish =
        !locked && !composer && widget.draft.validateStudent(student) == null;
    return PopScope(
      canPop: !composer,
      onPopInvokedWithResult: (popped, result) async {
        if (!popped && await discardComposer() && context.mounted) {
          Navigator.pop(context);
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: 'طلاب الحصة',
            onPressed: () async {
              if (await discardComposer() && context.mounted) {
                Navigator.pop(context);
              }
            },
            icon: const Icon(Icons.arrow_back),
          ),
          title: PageIdentity(
            student['name'],
            subtitle:
                'الطالب ${position + 1} من ${widget.draft.students.length}',
          ),
          actions: [
            if (widget.editSession != null)
              IconButton(
                tooltip: 'تعديل الحصة',
                onPressed: locked
                    ? null
                    : () async {
                        if (await discardComposer()) {
                          await widget.editSession!();
                          if (mounted) setState(() {});
                        }
                      },
                icon: const Icon(Icons.edit_outlined, size: 22),
              ),
          ],
        ),
        bottomNavigationBar: ActionDock(
          keyboardAware: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!canFinish &&
                  !locked &&
                  MediaQuery.viewInsetsOf(context).bottom == 0)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    composer
                        ? 'أضف الواجب المفتوح أو تخلّ عنه أولًا.'
                        : visible.isEmpty
                        ? 'أضف واجبًا جديدًا لإنهاء الطالب.'
                        : 'أدخل علامة رقمية لتقييم واحد على الأقل.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              Row(
                children: [
                  IconButton(
                    tooltip: '→ السابق',
                    onPressed: position == 0 ? null : () => step(-1),
                    icon: const Icon(Icons.arrow_back),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: canFinish ? finish : null,
                      icon: const Icon(Icons.check, size: 18),
                      label: const Text('إنهاء الطالب'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    tooltip: 'التالي ←',
                    onPressed: position == widget.draft.students.length - 1
                        ? null
                        : () => step(1),
                    icon: const Icon(Icons.arrow_forward),
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
              child: SingleChildScrollView(
                key: ValueKey(student['student_id']),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            dateLabel(
                              context,
                              widget.draft.payload['timestamp'],
                            ),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                        StatusPill(
                          student['evaluated_at'] == null
                              ? 'لم يُقيَّم بعد'
                              : 'تم التسجيل',
                          pending: student['evaluated_at'] == null,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    LinearProgressIndicator(
                      value: (position + 1) / widget.draft.students.length,
                      minHeight: 3,
                    ),
                    const SizedBox(height: 20),
                    if (storageError != null)
                      Text(
                        storageError!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    if (locked) const Text('نسخة للقراءة فقط'),
                    if (error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                    LayoutBuilder(
                      builder: (c, size) {
                        final evaluations = Panel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              const Text(
                                'تقييم اليوم',
                                style: TextStyle(
                                  fontSize: 19,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 12),
                              if (visible.isEmpty)
                                const Text(
                                  'لا توجد واجبات سابقة للتسميع. يمكنك إضافة واجب جديد ثم إنهاء الطالب.',
                                ),
                              for (final a in visible) assessment(a),
                              for (final a in attempts.where(
                                (a) => !assignments.any(
                                  (x) => x['id'] == a['quran_assignment_id'],
                                ),
                              ))
                                ListTile(
                                  title: const Text('واجب مؤرشف'),
                                  subtitle: Text(
                                    'العلامة ${a['grade']} · ${a['comment'] ?? ''}',
                                  ),
                                ),
                            ],
                          ),
                        );
                        if (size.maxWidth < 760) {
                          return Column(
                            children: [evaluations, nextAssignments()],
                          );
                        }
                        return Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(child: evaluations),
                            const SizedBox(width: 20),
                            Expanded(child: nextAssignments()),
                          ],
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
