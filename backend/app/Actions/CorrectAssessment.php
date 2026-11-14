<?php

namespace App\Actions;

use App\Models\QuranAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CorrectAssessment
{
    public function execute(QuranAssessment $assessment, array $data): QuranAssessment
    {
        Gate::authorize('update', $assessment);
        $values = Validator::make($data, ['grade' => ['required', 'numeric'], 'comment' => ['nullable', 'string']])->validate();

        return DB::transaction(function () use ($assessment, $values) {
            $record = QuranAssessment::withTrashed()->lockForUpdate()->findOrFail($assessment->id);
            $record->update($values);

            return $record;
        });
    }
}
