<?php

namespace App\Filament\Resources\StudentSessions\Pages;

use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use App\Filament\Resources\StudentSessions\StudentSessionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudentSession extends ViewRecord
{
    protected static string $resource = StudentSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [Action::make('assessments')->label('عرض التقييمات')->url(fn () => QuranAssessmentResource::getUrl('index', ['tableFilters' => ['student_session_id' => ['value' => $this->record->id]]])), EditAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
