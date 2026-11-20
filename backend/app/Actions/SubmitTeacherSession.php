<?php

namespace App\Actions;

use App\Http\Resources\TeacherSessionResource;
use App\Models\Group;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Support\QuranCatalog;
use App\Support\SchoolSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitTeacherSession
{
    public function execute(Teacher $teacher, array $data, ?int $sessionId = null)
    {
        return DB::transaction(function () use ($teacher, $data, $sessionId) {
            DB::table('school_sync_state')->where('id', 1)->lockForUpdate()->first();
            $hash = hash('sha256', json_encode($this->canonical(['session_id' => $sessionId, 'payload' => $data]), JSON_THROW_ON_ERROR));
            $previous = DB::table('teacher_submissions')->where('teacher_id', $teacher->id)->where('submission_id', $data['submission_id'])->first();
            if ($previous) {
                abort_unless(hash_equals($previous->payload_hash, $hash), 409, 'استُخدم معرّف الإرسال مع بيانات مختلفة.');

                $acknowledgement = json_decode($previous->acknowledgement, false, 512, JSON_THROW_ON_ERROR);
                $acceptedSession = $teacher->sessions()->find($acknowledgement->data->id);
                abort_unless($acceptedSession, 409, 'الحصة المؤكدة مؤرشفة أو لم تعد تابعة لهذا الأستاذ. احتفظ بالإقرار السابق والعمل المحلي.');

                return response()->json($acknowledgement, $sessionId ? 200 : 201);
            }

            $session = $sessionId ? $teacher->sessions()->findOrFail($sessionId) : null;
            abort_unless((int) $data['base_sync_revision'] === SchoolSync::revision(), 409, 'تغيرت بيانات المدرسة. احتفظ بالعمل المحلي وحمّل النسخة الحالية قبل إعادة الإرسال.');
            if ($session) {
                abort_unless((int) ($data['base_revision'] ?? 0) === (int) $session->revision, 409, 'تغيرت نسخة الحصة.');
                abort_unless(! $session->client_uuid || $session->client_uuid === $data['client_uuid'], 409, 'معرّف الحصة لا يطابق الحصة المحفوظة.');
            }
            $existingUuid = TeachingSession::withTrashed()->where('client_uuid', $data['client_uuid'])->first();
            abort_if($existingUuid && $existingUuid->id !== $session?->id, 409, 'معرّف الحصة مستخدم.');
            $group = Group::find($data['group_id']);
            abort_unless($group, 409, 'المجموعة لم تعد متاحة.');
            $studentIds = array_map('intval', array_column($data['students'], 'student_id'));
            if (! $session) {
                $expected = $group->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all();
                $this->requireSameIds($expected, $studentIds, 'students', 'يجب أن يطابق سجل الحصة طلاب المجموعة الحالية.');
            } else {
                $this->requireSameIds($session->studentSessions()->pluck('student_id')->map(fn ($id) => (int) $id)->all(), $studentIds, 'students', 'يجب الاحتفاظ بطلاب الحصة المحفوظة؛ تغيير المجموعة لا يعيد إنشاء سجلها.');
            }
            $values = ['group_id' => $group->id, 'timestamp' => $data['timestamp'], 'status' => 'completed'];
            $session ??= new TeachingSession(['teacher_id' => $teacher->id]);
            $session->fill($values)->forceFill(['client_uuid' => $data['client_uuid'], 'revision' => $sessionId ? $session->revision + 1 : 1])->save();
            $mapping = ['session_id' => $session->id, 'assignments' => [], 'assessments' => []];
            $seenAssignments = [];
            $seenAssessments = [];
            $repeatDecisions = [];
            $assignmentStates = [];
            foreach ($data['students'] as $index => $work) {
                $path = "students.$index";
                $student = Student::find($work['student_id']);
                abort_unless($student, 409, 'أحد الطلاب لم يعد متاحًا.');
                if (! $work['evaluated_at'] && (count($work['assessments']) || count($work['assignments']))) {
                    $this->invalid("$path.evaluated_at", 'أنهِ الطالب قبل إرسال واجباته أو تقييماته؛ الطالب غير المنتهي يسجل غائبًا.');
                }
                $roster = StudentSession::withTrashed()->where('session_id', $session->id)->where('student_id', $student->id)->first();
                abort_if($roster?->trashed(), 409, 'سجل الطالب مؤرشف ولا يمكن إعادة إنشائه.');
                $roster ??= new StudentSession(['session_id' => $session->id, 'student_id' => $student->id]);
                $roster->fill(['evaluated_at' => $work['evaluated_at'], 'attendance_status' => $work['evaluated_at'] ? 'present' : 'absent'])->save();
                $writtenAssignments = [];
                foreach ($work['assignments'] as $i => $input) {
                    $field = "$path.assignments.$i";
                    $assignment = $this->assignment($student, $input, $field, $session);
                    if (in_array($assignment->id, $seenAssignments, true)) {
                        $this->invalid($field, 'الواجب مكرر في الإرسال.');
                    }
                    $seenAssignments[] = $assignment->id;
                    $writtenAssignments[] = $assignment->id;
                    $assignmentStates[$assignment->id] = $assignment;
                    if ($assignment->client_uuid) {
                        $mapping['assignments'][$assignment->client_uuid] = $assignment->id;
                    }
                }
                foreach ($work['removed_assignment_ids'] ?? [] as $id) {
                    $assignment = $student->assignments()->find($id);
                    if (! $assignment || in_array($assignment->id, $writtenAssignments, true) || $assignment->assessments()->withTrashed()->exists()) {
                        $this->invalid("$path.removed_assignment_ids", 'يمكن حذف واجب غير مقيم فقط، ولا يمكن حفظه وحذفه معًا.');
                    }
                    $assignment->delete();
                }
                $originalAttempts = $roster->assessments()->pluck('id')->all();
                $submittedAttempts = [];
                foreach ($work['assessments'] as $i => $input) {
                    $field = "$path.assessments.$i";
                    $assignment = $this->findAssignment($student, $input['quran_assignment_id'] ?? null, $input['assignment_client_uuid'] ?? null, $field);
                    if (! $assignment) {
                        $this->invalid($field, 'الواجب غير موجود. أرسل الحصة المصدر أولًا إذا أُنشئ دون اتصال.');
                    }
                    $attempt = ! empty($input['id']) ? QuranAssessment::withTrashed()->find($input['id']) : (! empty($input['client_uuid']) ? QuranAssessment::withTrashed()->where('client_uuid', $input['client_uuid'])->first() : null);
                    if (! empty($input['id']) && ! $attempt) {
                        $this->invalid("$field.id", 'التقييم غير موجود.');
                    }
                    if ($attempt && ($attempt->trashed() || $attempt->student_session_id !== $roster->id)) {
                        $this->invalid($field, 'اختر تقييمًا فعالًا تابعًا لهذا الطالب في هذه الحصة.');
                    }
                    // Keep historical/admin attempts editable, but teachers cannot add
                    // a second assessment of the same assignment in this session.
                    if ((! $attempt || $attempt->quran_assignment_id !== $assignment->id)
                        && $roster->assessments()->withTrashed()->where('quran_assignment_id', $assignment->id)->exists()) {
                        $this->invalid($field, 'لهذا الواجب تقييم في الحصة نفسها. عدّل التقييم الموجود؛ إعادة التسميع تكون في حصة لاحقة.');
                    }
                    $this->checkUuid($attempt, $input['client_uuid'] ?? null, $field);
                    $attempt ??= new QuranAssessment(['student_session_id' => $roster->id]);
                    $attempt->fill(['quran_assignment_id' => $assignment->id, 'grade' => $input['grade'], 'comment' => $input['comment'] ?? null]);
                    if (! empty($input['client_uuid'])) {
                        $attempt->forceFill(['client_uuid' => $input['client_uuid']]);
                    }
                    $attempt->save();
                    if (array_key_exists('needs_repeat', $input)) {
                        $repeat = (bool) $input['needs_repeat'];
                        if (array_key_exists($assignment->id, $repeatDecisions) && $repeatDecisions[$assignment->id] !== $repeat) {
                            $this->invalid($field.'.needs_repeat', 'حالة الإعادة تخص الواجب كله؛ اختر حالة واحدة له.');
                        }
                        $repeatDecisions[$assignment->id] = $repeat;
                        $assignment->update(['is_passed' => ! $repeat]);
                    }
                    $assignmentStates[$assignment->id] = $assignment;

                    if (in_array($attempt->id, $seenAssessments, true)) {
                        $this->invalid($field, 'التقييم مكرر في الإرسال.');
                    }
                    $seenAssessments[] = $attempt->id;
                    $submittedAttempts[] = $attempt->id;
                    if ($attempt->client_uuid) {
                        $mapping['assessments'][$attempt->client_uuid] = $attempt->id;
                    }
                }
                if (array_diff($originalAttempts, $submittedAttempts)) {
                    $this->invalid("$path.assessments", 'احتفظ بجميع تقييمات الحصة المحفوظة. لا يجوز إسقاط محاولة سابقة.');
                }
            }
            $mapping['assignments'] = (object) $mapping['assignments'];
            $mapping['assessments'] = (object) $mapping['assessments'];
            $ack = ['submission_id' => $data['submission_id'], 'data' => (new TeacherSessionResource($session->fresh()->load(['group', 'studentSessions.student', 'studentSessions.assessments.assignment', 'createdAssignments' => fn ($query) => $query->withTrashed()->with('student')])))->resolve(), 'id_mapping' => $mapping, 'assignment_states' => array_values(array_map(fn ($assignment) => $assignment->fresh()->only(['id', 'student_id', 'client_uuid', 'is_passed']), $assignmentStates)), 'meta' => SchoolSync::meta()];
            DB::table('teacher_submissions')->insert(['teacher_id' => $teacher->id, 'submission_id' => $data['submission_id'], 'payload_hash' => $hash, 'acknowledgement' => json_encode($ack, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return response()->json($ack, $sessionId ? 200 : 201);
        }, 3);
    }

    private function assignment(Student $student, array $input, string $field, TeachingSession $session): QuranAssignment
    {
        $assignment = $this->findAssignment($student, $input['id'] ?? null, $input['client_uuid'] ?? null, $field);
        if (! empty($input['id']) && ! $assignment) {
            $this->invalid("$field.id", 'الواجب غير موجود.');
        }
        $this->checkUuid($assignment, $input['client_uuid'] ?? null, $field);
        if ($input['to_ayah'] < $input['from_ayah']) {
            $this->invalid("$field.to_ayah", 'حدّد نطاق آيات صحيحًا للسورة.');
        }
        $input['surah_name'] = QuranCatalog::arabicName($input['surah_name']) ?? $input['surah_name'];
        $passage = array_intersect_key($input, array_flip(['surah_name', 'from_ayah', 'to_ayah', 'type']));
        if ($assignment && $assignment->assessments()->withTrashed()->exists()) {
            foreach ($passage as $key => $value) {
                if ((string) $assignment->$key !== (string) $value) {
                    $this->invalid($field, 'احفظ مقطع الواجب المقيم كما هو. أنشئ واجبًا منفصلًا لمقطع آخر.');
                }
            }
        }
        if (! $assignment) {
            $assignment = new QuranAssignment(['student_id' => $student->id]);
            $assignment->forceFill(['created_in_session_id' => $session->id]);
        }
        $assignment->fill($passage + ['is_passed' => $input['is_passed']]);
        if (! empty($input['client_uuid'])) {
            $assignment->forceFill(['client_uuid' => $input['client_uuid']]);
        }
        $assignment->save();

        return $assignment;
    }

    private function findAssignment(Student $student, ?int $id, ?string $uuid, string $field): ?QuranAssignment
    {
        $assignment = $id ? QuranAssignment::withTrashed()->find($id) : ($uuid ? QuranAssignment::withTrashed()->where('client_uuid', $uuid)->first() : null);
        if ($assignment && ($assignment->trashed() || $assignment->student_id !== $student->id)) {
            $this->invalid($field, 'اختر واجبًا فعالًا لنفس الطالب.');
        }
        if ($assignment && $uuid && $assignment->client_uuid && $assignment->client_uuid !== $uuid) {
            $this->invalid($field, 'معرّف الواجب لا يطابق السجل.');
        }
        if ($id && $uuid) {
            $other = QuranAssignment::withTrashed()->where('client_uuid', $uuid)->first();
            if ($other && $other->id !== $id) {
                $this->invalid($field, 'معرّف الواجب مستخدم لسجل آخر.');
            }
        }

        return $assignment;
    }

    private function checkUuid($record, ?string $uuid, string $field): void
    {
        if (! $record && ! $uuid) {
            $this->invalid("$field.client_uuid", 'يلزم معرّف ثابت للسجل الجديد.');
        }
        if ($record?->client_uuid && $uuid && $record->client_uuid !== $uuid) {
            $this->invalid("$field.client_uuid", 'المعرّف لا يطابق السجل المحفوظ.');
        }
        if ($record instanceof QuranAssessment && $uuid) {
            $other = QuranAssessment::withTrashed()->where('client_uuid', $uuid)->first();
            if ($other && $other->id !== $record->id) {
                $this->invalid($field, 'معرّف التقييم مستخدم.');
            }
        }
    }

    private function requireSameIds(array $expected, array $actual, string $field, string $message): void
    {
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            $this->invalid($field, $message);
        }
    }

    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $data;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
