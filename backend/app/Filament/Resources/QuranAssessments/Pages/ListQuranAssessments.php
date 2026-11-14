<?php

namespace App\Filament\Resources\QuranAssessments\Pages;

use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuranAssessments extends ListRecords
{
    protected static string $resource = QuranAssessmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
