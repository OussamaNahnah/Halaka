<?php

namespace App\Filament\Resources\QuranAssignments\Pages;

use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuranAssignment extends ViewRecord
{
    protected static string $resource = QuranAssignmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [Action::make('assessments')->label('عرض التقييمات')->url(fn () => QuranAssessmentResource::getUrl('index', ['tableFilters' => ['quran_assignment_id' => ['value' => $this->record->id]]])), EditAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
