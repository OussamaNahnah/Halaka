<?php

namespace App\Filament\Resources\TeachingSessions\Pages;

use App\Actions\SaveTeachingSession;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeachingSession extends CreateRecord
{
    protected static string $resource = TeachingSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTeachingSession::class)->execute($data);
    }
}
