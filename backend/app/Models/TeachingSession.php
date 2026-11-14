<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['teacher_id', 'group_id', 'timestamp', 'status'])]
class TeachingSession extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function studentSessions(): HasMany
    {
        return $this->hasMany(StudentSession::class, 'session_id');
    }

    public function assessments(): HasManyThrough
    {
        return $this->hasManyThrough(QuranAssessment::class, StudentSession::class, 'session_id', 'student_session_id')->withTrashedParents();
    }
}
