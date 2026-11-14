<?php

namespace App\Filament\Schemas;

use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rule;

class AssessmentSchema
{
    public static function components(Student|TeachingSession|null $owner = null): array
    {
        return [
            Select::make('student_session_id')->label('الطالب وحصة التسميع')->options(fn () => StudentSession::withTrashed()->with(['student', 'session'])->when($owner, fn ($query) => $query->where($owner instanceof Student ? 'student_id' : 'session_id', $owner->id))->get()->mapWithKeys(fn ($row) => [$row->id => $row->student->name.' · '.$row->session->timestamp->format('Y-m-d H:i').($row->trashed() ? ' (محذوف)' : '')]))->searchable()->required()->rule($owner ? Rule::exists('student_sessions', 'id')->where($owner instanceof Student ? 'student_id' : 'session_id', $owner->id) : Rule::exists('student_sessions', 'id'))->live()->afterStateUpdated(fn (Set $set) => $set('quran_assignment_id', null)),
            Select::make('quran_assignment_id')->label('الواجب')->options(function (Get $get) {
                $studentId = StudentSession::withTrashed()->find($get('student_session_id'))?->student_id;

                return QuranAssignment::withTrashed()->where('student_id', $studentId)->get()->mapWithKeys(fn ($assignment) => [$assignment->id => $assignment->surah_name.' · '.$assignment->from_ayah.'–'.$assignment->to_ayah.' · #'.$assignment->id.($assignment->trashed() ? ' (محذوف)' : '')]);
            })->searchable()->required()->rule(fn (Get $get) => Rule::exists('quran_assignments', 'id')->where('student_id', StudentSession::withTrashed()->find($get('student_session_id'))?->student_id)),
            TextInput::make('grade')->label('العلامة')->numeric()->step('any')->required(),
            Textarea::make('comment')->label('التعليق')->rows(3),
        ];
    }
}
