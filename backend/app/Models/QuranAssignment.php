<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['student_id', 'surah_name', 'from_ayah', 'to_ayah', 'type', 'is_passed'])]
class QuranAssignment extends Model
{
    use SoftDeletes;

    protected $attributes = ['is_passed' => false];

    protected function casts(): array
    {
        return [
            'from_ayah' => 'integer',
            'to_ayah' => 'integer',
            'is_passed' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QuranAssessment::class);
    }
}
