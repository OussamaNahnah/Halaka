<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['student_id', 'session_id', 'attendance_status', 'evaluated_at'])]
class StudentSession extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class, 'session_id')->withTrashed();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QuranAssessment::class);
    }
}
