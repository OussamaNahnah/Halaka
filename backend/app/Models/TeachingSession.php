<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teacher_id', 'group_id', 'timestamp', 'status'])]
class TeachingSession extends Model
{
    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function studentSessions(): HasMany
    {
        return $this->hasMany(StudentSession::class, 'session_id');
    }
}
