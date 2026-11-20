<?php

namespace App\Models;

use App\Support\QuranCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['student_id', 'surah_name', 'from_ayah', 'to_ayah', 'type', 'is_passed'])]
class QuranAssignment extends Model
{
    use SoftDeletes;

    protected $attributes = ['is_passed' => false];

    protected function surahName(): Attribute
    {
        return Attribute::make(set: fn (string $value) => QuranCatalog::arabicName($value) ?? $value);
    }

    protected function casts(): array
    {
        return [
            'created_in_session_id' => 'integer',
            'from_ayah' => 'integer',
            'to_ayah' => 'integer',
            'is_passed' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function createdInSession(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class, 'created_in_session_id')->withTrashed();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QuranAssessment::class);
    }
}
