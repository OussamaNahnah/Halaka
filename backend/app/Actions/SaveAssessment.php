<?php

namespace App\Actions;

use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveAssessment
{
    public function execute(Student|TeachingSession $owner, array $data, ?QuranAssessment $assessment = null): QuranAssessment
    {
        $assessment ? Gate::authorize('update', $assessment) : Gate::authorize('create', QuranAssessment::class);
        $values = Validator::make($data, [
            'student_session_id' => ['required', 'integer', 'exists:student_sessions,id'],
            'quran_assignment_id' => ['required', 'integer', 'exists:quran_assignments,id'],
            'grade' => ['required', 'numeric'],
            'comment' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($owner, $values, $assessment) {
            $roster = StudentSession::withTrashed()->lockForUpdate()->findOrFail($values['student_session_id']);
            $assignment = QuranAssignment::withTrashed()->lockForUpdate()->findOrFail($values['quran_assignment_id']);
            if ($assignment->student_id !== $roster->student_id) {
                throw ValidationException::withMessages(['quran_assignment_id' => 'اختر واجبًا لنفس الطالب المسجل في الحصة.']);
            }
            if (($owner instanceof Student && $roster->student_id !== $owner->id) || ($owner instanceof TeachingSession && $roster->session_id !== $owner->id)) {
                throw ValidationException::withMessages(['student_session_id' => 'اختر سجلًا تابعًا للطالب أو الحصة المعروضة.']);
            }
            if ($assessment) {
                $assessment->update($values);

                return $assessment;
            }

            return QuranAssessment::create($values);
        });
    }
}
