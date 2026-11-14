<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assigned_in_student_session_id', 'surah_name', 'from_ayah', 'to_ayah', 'type', 'repeat_of_assessment_id'])]
class QuranAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'from_ayah' => 'integer',
            'to_ayah' => 'integer',
        ];
    }

    public function assignedInStudentSession(): BelongsTo
    {
        return $this->belongsTo(StudentSession::class, 'assigned_in_student_session_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QuranAssessment::class);
    }

    public function repeatOfAssessment(): BelongsTo
    {
        return $this->belongsTo(QuranAssessment::class, 'repeat_of_assessment_id');
    }
}
