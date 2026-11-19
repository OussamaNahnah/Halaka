import 'dart:convert';

import 'package:uuid/uuid.dart';

import 'api.dart';

const uuid = Uuid();
Json copy(Json value) =>
    Map<String, dynamic>.from(jsonDecode(jsonEncode(value)));
List<Json> rows(dynamic value) =>
    (value as List? ?? []).map((v) => Map<String, dynamic>.from(v)).toList();

class SessionDraft {
  final int? id;
  final Json payload;
  final List<Json> students;
  final Map<int, List<Json>> assignments;
  Json? pending;
  bool conflict = false;
  SessionDraft({
    this.id,
    required this.payload,
    required this.students,
    required this.assignments,
  });
  bool get locked => pending != null;

  String? validateStudent(Json student) {
    final attempts = rows(student['assessments']);
    if (attempts.any((a) => num.tryParse('${a['grade']}')?.isFinite != true)) {
      return 'أدخل درجة رقمية لكل تقييم';
    }
    final previous = assignments[student['student_id']] ?? [];
    if (attempts.isEmpty &&
        (previous.any((a) => a['is_passed'] != true) ||
            rows(student['assignments']).isEmpty)) {
      return 'قيّم واجباً أو أضف واجباً جديداً عند عدم وجود واجبات سابقة';
    }
    return null;
  }

  Json freeze() {
    if (pending != null) return pending!;
    final result = copy(payload);
    result['submission_id'] = uuid.v4();
    result['status'] = 'completed';
    result['students'] = students
        .map(
          (s) => {
            'student_id': s['student_id'],
            'evaluated_at': s['evaluated_at'],
            'assignments': s['evaluated_at'] == null
                ? []
                : rows(s['assignments']),
            'assessments': s['evaluated_at'] == null
                ? []
                : rows(s['assessments'])
                      .map(
                        (a) => <String, dynamic>{
                          for (final key in [
                            'id',
                            'client_uuid',
                            'quran_assignment_id',
                            'assignment_client_uuid',
                            'grade',
                            'comment',
                          ])
                            if (a.containsKey(key)) key: a[key],
                          'needs_repeat':
                              a['needs_repeat'] ??
                              (a.containsKey('is_passed')
                                  ? a['is_passed'] != true
                                  : false),
                        },
                      )
                      .toList(),
          },
        )
        .toList();
    pending = copy(result);
    return pending!;
  }
}
