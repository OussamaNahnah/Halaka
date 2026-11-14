<?php

namespace App\Filament\Resources\TeachingSessions\Pages;

use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeachingSessions extends ListRecords
{
    protected static string $resource = TeachingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
