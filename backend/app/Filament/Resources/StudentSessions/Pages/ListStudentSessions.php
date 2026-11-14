<?php

namespace App\Filament\Resources\StudentSessions\Pages;

use App\Filament\Resources\StudentSessions\StudentSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentSessions extends ListRecords
{
    protected static string $resource = StudentSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
