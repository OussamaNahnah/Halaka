<?php

namespace App\Actions;

use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveTeachingSession
{
    public function execute(array $data, ?TeachingSession $session = null): TeachingSession
    {
        $session ? Gate::authorize('update', $session) : Gate::authorize('create', TeachingSession::class);
        $values = Validator::make($data, [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'timestamp' => ['required', 'date'],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ])->validate();

        return DB::transaction(function () use ($values, $session) {
            $students = $values['student_ids'] ?? [];
            unset($values['student_ids']);
            if ($session) {
                $session->update($values);
            } else {
                $session = TeachingSession::create($values);
                foreach ($students as $studentId) {
                    $session->studentSessions()->create(['student_id' => $studentId]);
                }
            }
            if ($session->status === 'completed') {
                $session->studentSessions()->whereNull('evaluated_at')->where('attendance_status', 'not_marked')->update(['attendance_status' => 'absent']);
            }

            return $session;
        });
    }
}
