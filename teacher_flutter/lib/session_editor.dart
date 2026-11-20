import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'ui.dart';

Future<Json?> showSessionEditor(
  BuildContext context, {
  required List<Json> groups,
  int? groupId,
  String? timestamp,
  bool editing = false,
  bool allowGroupChange = true,
}) => showModalBottomSheet<Json>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  showDragHandle: true,
  builder: (_) => SessionEditor(
    groups: groups,
    groupId: groupId,
    timestamp: timestamp,
    editing: editing,
    allowGroupChange: allowGroupChange,
  ),
);

class SessionEditor extends StatefulWidget {
  final List<Json> groups;
  final int? groupId;
  final String? timestamp;
  final bool editing;
  final bool allowGroupChange;
  const SessionEditor({
    super.key,
    required this.groups,
    this.groupId,
    this.timestamp,
    this.editing = false,
    this.allowGroupChange = true,
  });
  @override
  State<SessionEditor> createState() => _SessionEditorState();
}

class _SessionEditorState extends State<SessionEditor> {
  int? group;
  late DateTime date;
  @override
  void initState() {
    super.initState();
    group =
        !widget.allowGroupChange ||
            widget.groups.any((g) => g['id'] == widget.groupId)
        ? widget.groupId
        : null;
    date =
        DateTime.tryParse(widget.timestamp ?? '')?.toLocal() ?? DateTime.now();
  }

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: BoxConstraints(
      maxHeight: MediaQuery.sizeOf(context).height * .8,
      maxWidth: 700,
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(20, 0, 8, 16),
          child: Row(
            children: [
              Expanded(
                child: PageIdentity(
                  widget.editing ? 'تعديل الحصة' : 'حصة جديدة',
                  subtitle: widget.editing
                      ? widget.allowGroupChange
                            ? 'تغيير المجموعة يعيد تحميل الطلاب'
                            : 'طلاب الحصة المحفوظة ثابتون'
                      : 'اختر المجموعة وموعد الحصة',
                ),
              ),
              IconButton(
                tooltip: 'إغلاق',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
        ),
        Flexible(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (!widget.allowGroupChange)
                  InputDecorator(
                    decoration: const InputDecoration(labelText: 'المجموعة'),
                    child: Text(
                      widget.groups
                              .where((g) => g['id'] == group)
                              .firstOrNull?['name'] ??
                          'المجموعة الأصلية',
                    ),
                  )
                else
                  DropdownButtonFormField<int>(
                    initialValue: group,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'المجموعة'),
                    hint: const Text('اختر مجموعة'),
                    items: widget.groups
                        .map(
                          (g) => DropdownMenuItem<int>(
                            value: g['id'],
                            child: Text(
                              g['name'],
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => group = v),
                  ),
                const SizedBox(height: 20),
                const Text(
                  'موعد الحصة',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await showDatePicker(
                      context: context,
                      initialDate: date,
                      firstDate: DateTime(2000),
                      lastDate: DateTime(2100),
                    );
                    if (picked != null) {
                      setState(
                        () => date = DateTime(
                          picked.year,
                          picked.month,
                          picked.day,
                          date.hour,
                          date.minute,
                        ),
                      );
                    }
                  },
                  icon: const Icon(Icons.calendar_today_outlined, size: 18),
                  label: Text(dateLabel(context, date)),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await showTimePicker(
                      context: context,
                      initialTime: TimeOfDay.fromDateTime(date),
                    );
                    if (picked != null) {
                      setState(
                        () => date = DateTime(
                          date.year,
                          date.month,
                          date.day,
                          picked.hour,
                          picked.minute,
                        ),
                      );
                    }
                  },
                  icon: const Icon(Icons.schedule_outlined, size: 18),
                  label: Text(timeLabel(context, date)),
                ),
                if (widget.groups.isEmpty && widget.allowGroupChange)
                  const Padding(
                    padding: EdgeInsets.only(top: 12),
                    child: Text('لا توجد مجموعات متاحة. تواصل مع الإدارة.'),
                  ),
              ],
            ),
          ),
        ),
        ActionDock(
          child: SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: widget.allowGroupChange && group == null
                  ? null
                  : () => Navigator.pop(context, <String, dynamic>{
                      'group_id': group,
                      'timestamp': date.toUtc().toIso8601String(),
                    }),
              child: Text(widget.editing ? 'حفظ التعديل' : 'بدء'),
            ),
          ),
        ),
      ],
    ),
  );
}
