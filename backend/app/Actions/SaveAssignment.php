<?php

namespace App\Actions;

use App\Models\QuranAssignment;
use App\Models\Student;
use App\Support\QuranCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAssignment
{
    public function execute(Student $student, array $data, ?QuranAssignment $assignment = null, bool $allowStudentChange = false): QuranAssignment
    {
        $assignment ? Gate::authorize('update', $assignment) : Gate::authorize('create', QuranAssignment::class);
        $values = Validator::make($data, [
            'surah_name' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! QuranCatalog::verseCount($value)) {
                    $fail('اختر سورة صحيحة.');
                }
            }],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'gte:from_ayah', 'max:'.(QuranCatalog::verseCount($data['surah_name'] ?? '') ?? 0)],
            'type' => ['required', Rule::in(['new_memorization', 'revision'])],
            'is_passed' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($student, $assignment, $values, $allowStudentChange) {
            if ($assignment) {
                $assignment = QuranAssignment::withTrashed()->lockForUpdate()->findOrFail($assignment->id);
                if ($assignment->student_id !== $student->id) {
                    abort_unless($allowStudentChange, 403);
                    if ($assignment->assessments()->withTrashed()->whereHas('studentSession', fn ($query) => $query->where('student_id', '!=', $student->id))->exists()) {
                        throw ValidationException::withMessages(['student_id' => 'تقييمات الواجب تخص طالبًا آخر. صحح روابط التقييمات أولًا.']);
                    }
                }
                $assignment->update($values + ['student_id' => $student->id]);

                return $assignment;
            }

            return $student->assignments()->create($values);
        });
    }
}
