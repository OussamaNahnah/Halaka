<?php

namespace App\Filament\Resources\QuranAssignments\Pages;

use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuranAssignments extends ListRecords
{
    protected static string $resource = QuranAssignmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
