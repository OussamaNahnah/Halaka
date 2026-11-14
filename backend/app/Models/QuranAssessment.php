<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_session_id', 'quran_assignment_id', 'grade', 'comment'])]
class QuranAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'grade' => 'float',
        ];
    }

    public function studentSession(): BelongsTo
    {
        return $this->belongsTo(StudentSession::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(QuranAssignment::class, 'quran_assignment_id');
    }

    public function repeatAssignments(): HasMany
    {
        return $this->hasMany(QuranAssignment::class, 'repeat_of_assessment_id');
    }
}
