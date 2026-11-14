<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'parent_phone'])]
class Student extends Model
{
    use SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function studentSessions(): HasMany
    {
        return $this->hasMany(StudentSession::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(QuranAssignment::class);
    }

    public function assessments(): HasManyThrough
    {
        return $this->hasManyThrough(QuranAssessment::class, QuranAssignment::class, 'student_id', 'quran_assignment_id')->withTrashedParents();
    }
}
