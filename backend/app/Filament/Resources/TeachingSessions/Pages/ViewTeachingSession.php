<?php

namespace App\Filament\Resources\TeachingSessions\Pages;

use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTeachingSession extends ViewRecord
{
    protected static string $resource = TeachingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
