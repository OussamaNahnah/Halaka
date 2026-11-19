<?php

namespace App\Http\Requests;

use App\Support\QuranCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->teacher;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'uuid'], 'client_uuid' => ['required', 'uuid'],
            'base_sync_revision' => ['required', 'integer', 'min:0'], 'base_revision' => [Rule::requiredIf($this->route('session') !== null), 'nullable', 'integer', 'min:1'],
            'teacher_id' => ['prohibited'], 'status' => ['required', Rule::in(['completed'])],
            'group_id' => ['required', 'integer', 'min:1'], 'timestamp' => ['required', 'date'],
            'students' => ['required', 'array', 'min:1', 'max:500'], 'students.*' => ['array:student_id,evaluated_at,assignments,assessments,removed_assignment_ids'],
            'students.*.student_id' => ['required', 'integer', 'min:1', 'distinct'], 'students.*.evaluated_at' => ['present', 'nullable', 'date'],
            'students.*.assignments' => ['present', 'array', 'max:500'], 'students.*.assignments.*' => ['array:id,client_uuid,surah_name,from_ayah,to_ayah,type,is_passed'],
            'students.*.assignments.*.id' => ['nullable', 'integer', 'min:1'], 'students.*.assignments.*.client_uuid' => ['nullable', 'uuid'],
            'students.*.assignments.*.surah_name' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! QuranCatalog::verseCount($value)) {
                    $fail('اختر سورة صحيحة.');
                }
            }],
            'students.*.assignments.*.from_ayah' => ['required', 'integer', 'min:1'], 'students.*.assignments.*.to_ayah' => ['required', 'integer', 'min:1'],
            'students.*.assignments.*.type' => ['required', Rule::in(['new_memorization', 'revision'])], 'students.*.assignments.*.is_passed' => ['required', 'boolean'],
            'students.*.assessments' => ['present', 'array', 'max:1000'], 'students.*.assessments.*' => ['array:id,client_uuid,quran_assignment_id,assignment_client_uuid,grade,comment,needs_repeat'],
            'students.*.assessments.*.needs_repeat' => ['sometimes', 'boolean'],
            'students.*.assessments.*.id' => ['nullable', 'integer', 'min:1'], 'students.*.assessments.*.client_uuid' => ['nullable', 'uuid'],
            'students.*.assessments.*.quran_assignment_id' => ['nullable', 'integer', 'min:1'], 'students.*.assessments.*.assignment_client_uuid' => ['nullable', 'uuid'],
            'students.*.assessments.*.grade' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if (! is_finite((float) $value)) {
                    $fail('أدخل عددًا صالحًا.');
                }
            }], 'students.*.assessments.*.comment' => ['nullable', 'string', 'max:10000'],
            'students.*.removed_assignment_ids' => ['sometimes', 'array', 'max:500'], 'students.*.removed_assignment_ids.*' => ['integer', 'distinct'],
        ];
    }
}
