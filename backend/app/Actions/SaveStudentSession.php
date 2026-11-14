<?php

namespace App\Actions;

use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveStudentSession
{
    public function execute(Student|TeachingSession $owner, array $data, ?StudentSession $record = null): StudentSession
    {
        $record ? Gate::authorize('update', $record) : Gate::authorize('create', StudentSession::class);
        $data[$owner instanceof Student ? 'student_id' : 'session_id'] = $owner->id;
        $values = Validator::make($data, [
            'student_id' => ['required', 'integer', 'exists:students,id', Rule::unique('student_sessions', 'student_id')->where('session_id', $data['session_id'] ?? null)->ignore($record?->id)],
            'session_id' => ['required', 'integer', 'exists:teaching_sessions,id'],
            'attendance_status' => ['required', Rule::in(['not_marked', 'present', 'absent'])],
            'evaluated_at' => ['nullable', 'date'],
        ])->validate();
        if ($record && $record->assessments()->withTrashed()->whereHas('assignment', fn ($query) => $query->where('student_id', '!=', $values['student_id']))->exists()) {
            throw ValidationException::withMessages(['student_id' => 'تقييمات هذا السجل تخص طالبًا آخر. صحح روابط التقييمات أولًا.']);
        }
        $session = TeachingSession::withTrashed()->findOrFail($values['session_id']);
        if ($session->status === 'completed' && $values['attendance_status'] === 'not_marked') {
            throw ValidationException::withMessages(['attendance_status' => 'حدّد حاضرًا أو غائبًا للحصة المكتملة.']);
        }
        $values['evaluated_at'] = $values['attendance_status'] === 'present' ? ($values['evaluated_at'] ?? $record?->evaluated_at ?? now()) : null;

        return DB::transaction(function () use ($record, $values) {
            if ($record) {
                $record->update($values);

                return $record;
            }

            return StudentSession::create($values);
        });
    }
}
