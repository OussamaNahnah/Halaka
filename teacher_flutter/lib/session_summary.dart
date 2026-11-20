import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'draft.dart';
import 'ui.dart';

/// Accepted server data only. Viewing never creates or persists a draft.
class SessionSummary extends StatefulWidget {
  final TeacherApi api;
  final int sessionId;
  final bool canEdit;
  const SessionSummary({
    super.key,
    required this.api,
    required this.sessionId,
    required this.canEdit,
  });
  @override
  State<SessionSummary> createState() => _SessionSummaryState();
}

class _SessionSummaryState extends State<SessionSummary> {
  Json? session;
  Map<int, List<Json>> assignments = {};
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.api.request(
        'GET',
        '/sessions/${widget.sessionId}',
      );
      final data = Map<String, dynamic>.from(response['data']);
      final context = <int, List<Json>>{};
      for (final assignment in rows(data['assessed_assignments'])) {
        context.putIfAbsent(assignment['student_id'], () => []).add(assignment);
      }
      if (mounted) {
        setState(() {
          session = data;
          assignments = context;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String passage(Json? a, dynamic id) => a == null
      ? 'واجب مؤرشف أو غير متاح (#$id)'
      : '${a['type'] == 'revision' ? 'مراجعة' : 'حفظ جديد'} · ${a['surah_name']} · الآيات ${a['from_ayah']}–${a['to_ayah']}';
  String attendance(Json student) => student['attendance_status'] == 'present'
      ? 'حاضر'
      : student['attendance_status'] == 'absent'
      ? 'غائب'
      : 'لم يُسجّل الحضور';
  Widget sectionHeading(
    String title,
    int count,
    IconData icon, {
    String? hint,
  }) => Padding(
    padding: const EdgeInsets.only(top: 16, bottom: 10),
    child: Row(
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        if (hint != null)
          Text(hint, style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(width: 8),
        StatusPill('$count'),
      ],
    ),
  );
  Widget passageText(Json? assignment, dynamic id) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        assignment == null
            ? 'واجب #$id'
            : '${assignment['type'] == 'revision' ? 'مراجعة' : 'حفظ جديد'} · ${assignment['surah_name']}',
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      if (assignment != null)
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Wrap(
            spacing: 4,
            children: [
              Text('الآيات', style: Theme.of(context).textTheme.bodySmall),
              Text(
                '${assignment['from_ayah']}–${assignment['to_ayah']}',
                textDirection: TextDirection.ltr,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
    ],
  );
  Widget assessmentRow(Json attempt, List<Json> work) {
    final assignment = work
        .where((a) => a['id'] == attempt['quran_assignment_id'])
        .firstOrNull;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: passageText(assignment, attempt['quran_assignment_id']),
              ),
              const SizedBox(width: 12),
              Semantics(
                label: 'العلامة: ${attempt['grade'] ?? 'غير مسجلة'}',
                child: Column(
                  children: [
                    Text(
                      '${attempt['grade'] ?? '—'}',
                      textDirection: TextDirection.ltr,
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                    Text(
                      'العلامة',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
          if ('${attempt['comment'] ?? ''}'.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.notes_outlined, size: 17),
                  const SizedBox(width: 6),
                  Expanded(child: Text(attempt['comment'])),
                ],
              ),
            ),
          if (assignment?['archived'] == true)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Align(
                alignment: AlignmentDirectional.centerStart,
                child: StatusPill('مؤرشف', pending: true),
              ),
            ),
        ],
      ),
    );
  }

  Widget createdRow(Json assignment) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: passageText(assignment, assignment['id'])),
        const SizedBox(width: 8),
        StatusPill(
          assignment['archived'] == true
              ? 'مؤرشف'
              : assignment['is_passed'] == true
              ? 'مجتاز'
              : 'قيد الإنجاز',
          pending:
              assignment['archived'] == true || assignment['is_passed'] != true,
        ),
      ],
    ),
  );
  Widget studentCard(Json student) {
    final work = assignments[student['student_id']] ?? [];
    final attempts = rows(student['assessments']);
    final created = rows(session?['created_assignments'])
        .where((a) => a['student_id'] == student['student_id'])
        .toList();
    final present = student['attendance_status'] == 'present';
    return Card(
      child: ExpansionTile(
        key: PageStorageKey('summary-student-${student['student_id']}'),
        leading: CircleAvatar(
          radius: 20,
          backgroundColor: (present ? forest : gold).withValues(alpha: .12),
          child: Icon(
            present ? Icons.person_outline : Icons.person_off_outlined,
            size: 22,
            color: Theme.of(context).colorScheme.primary,
          ),
        ),
        title: Text(
          student['name'],
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        ),
        subtitle: Text(
          '${attendance(student)} · ${attempts.length} تقييم · ${created.length} واجب${student['archived'] == true ? ' · مؤرشف' : ''}',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        expandedCrossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Divider(height: 1),
          if (attempts.isNotEmpty) ...[
            sectionHeading(
              'التقييمات',
              attempts.length,
              Icons.fact_check_outlined,
            ),
            for (final attempt in attempts) assessmentRow(attempt, work),
          ],
          if (created.isNotEmpty) ...[
            sectionHeading(
              'الواجبات الجديدة',
              created.length,
              Icons.menu_book_outlined,
              hint: 'الحالة الآن',
            ),
            for (final assignment in created) createdRow(assignment),
          ],
          if (attempts.isEmpty && created.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Text(
                student['attendance_status'] == 'absent'
                    ? 'لم يحضر الطالب هذه الحصة.'
                    : 'لا توجد تقييمات أو واجبات مسجلة.',
              ),
            ),
        ],
      ),
    );
  }

  Widget attendanceMetric(String label, int count, Color color) => Expanded(
    child: Semantics(
      label: '$label: $count',
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          children: [
            Text(
              '$count',
              key: ValueKey('summary-$label-count'),
              style: TextStyle(
                fontSize: 23,
                fontWeight: FontWeight.w700,
                color: Theme.of(context).brightness == Brightness.dark
                    ? Theme.of(context).colorScheme.onSurface
                    : color,
              ),
            ),
            const SizedBox(height: 2),
            Text(label, style: const TextStyle(fontSize: 13)),
          ],
        ),
      ),
    ),
  );
  Widget sessionHeader(Json data, int total, int present, int absent) => Panel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                data['group']?['name'] ?? 'حصة',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 10),
            StatusPill(
              data['status'] == 'completed' ? 'تم الإرسال' : 'قيد الإنجاز',
            ),
          ],
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 4,
          children: [
            Text(
              dateLabel(context, data['timestamp']),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            Text(
              timeLabel(context, data['timestamp']),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
        if (data['group']?['archived'] == true)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: StatusPill('المجموعة مؤرشفة', pending: true),
            ),
          ),
        const SizedBox(height: 16),
        Row(
          children: [
            attendanceMetric('الطلاب', total, forest),
            const SizedBox(width: 8),
            attendanceMetric('حاضر', present, forest),
            const SizedBox(width: 8),
            attendanceMetric('غائب', absent, const Color(0xff795c1e)),
          ],
        ),
        if (total > present + absent)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'لم يُسجّل حضور ${total - present - absent} طلاب',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final data = session;
    final students = rows(data?['students']);
    final present = students
        .where((s) => s['attendance_status'] == 'present')
        .length;
    final absent = students
        .where((s) => s['attendance_status'] == 'absent')
        .length;
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          tooltip: 'الحصص',
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.arrow_back),
        ),
        title: const PageIdentity('ملخص الحصة'),
        actions: [
          IconButton(
            tooltip: 'تحديث الملخص',
            onPressed: loading ? null : load,
            icon: const Icon(Icons.refresh),
          ),
          if (data != null)
            IconButton(
              tooltip: widget.canEdit
                  ? 'تعديل الحصة'
                  : 'أكمل الحصة الحالية قبل التعديل',
              onPressed: widget.canEdit && !loading
                  ? () => Navigator.pop(context, true)
                  : null,
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
      ),
      body: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 800),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (loading) const LinearProgressIndicator(),
                if (error != null)
                  Panel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                        TextButton(
                          onPressed: loading ? null : load,
                          child: const Text('إعادة المحاولة'),
                        ),
                      ],
                    ),
                  ),
                if (data != null) ...[
                  sessionHeader(data, students.length, present, absent),
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: Text(
                      'الطلاب',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (students.isEmpty)
                    const Text('لا يوجد طلاب في هذه الحصة.'),
                  for (final student in students) studentCard(student),
                  if (rows(data['created_assignments']).any(
                    (a) => !students.any(
                      (student) => student['student_id'] == a['student_id'],
                    ),
                  ))
                    Panel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            'واجبات مرتبطة بطلاب خارج القائمة الحالية',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                          for (final a
                              in rows(data['created_assignments']).where(
                                (a) => !students.any(
                                  (student) =>
                                      student['student_id'] == a['student_id'],
                                ),
                              ))
                            Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                '${a['student_name']} · ${passage(a, a['id'])}${a['archived'] == true ? ' · مؤرشف' : ''}',
                              ),
                            ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
