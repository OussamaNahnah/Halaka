<?php

namespace App\Filament\Actions;

use App\Actions\CorrectAssessment;
use App\Models\QuranAssessment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;

class AssessmentActions
{
    public static function correct(): Action
    {
        return Action::make('correctAssessment')->label('تصحيح التقييم')->icon('heroicon-o-pencil-square')
            ->visible(fn (QuranAssessment $record) => auth()->user()?->can('update', $record) ?? false)
            ->modalHeading('تصحيح العلامة والتعليق')
            ->schema([
                TextEntry::make('student')->label('الطالب')->state(fn (QuranAssessment $record) => $record->assignment->student->name),
                TextEntry::make('passage')->label('المقطع')->state(fn (QuranAssessment $record) => $record->assignment->surah_name.' · '.$record->assignment->from_ayah.'–'.$record->assignment->to_ayah),
                TextEntry::make('session')->label('حصة التقييم')->state(fn (QuranAssessment $record) => $record->studentSession->session->timestamp->format('Y-m-d H:i')),
                TextInput::make('grade')->label('العلامة')->numeric()->step('any')->required(),
                Textarea::make('comment')->label('التعليق')->rows(3),
            ])
            ->fillForm(fn (QuranAssessment $record) => ['grade' => $record->grade, 'comment' => $record->comment])
            ->action(function (QuranAssessment $record, array $data): void {
                app(CorrectAssessment::class)->execute($record, $data);
                $record->refresh();
                Notification::make()->title('تم حفظ التصحيح')->success()->send();
            });
    }
}
