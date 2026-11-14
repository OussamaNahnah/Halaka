<?php

namespace App\Filament\Resources\QuranAssessments\Pages;

use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuranAssessment extends ViewRecord
{
    protected static string $resource = QuranAssessmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
