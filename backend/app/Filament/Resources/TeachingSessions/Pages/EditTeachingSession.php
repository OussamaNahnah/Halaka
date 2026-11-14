<?php

namespace App\Filament\Resources\TeachingSessions\Pages;

use App\Actions\SaveTeachingSession;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeachingSession extends EditRecord
{
    protected static string $resource = TeachingSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveTeachingSession::class)->execute($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
