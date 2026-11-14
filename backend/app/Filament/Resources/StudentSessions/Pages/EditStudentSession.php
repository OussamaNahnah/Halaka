<?php

namespace App\Filament\Resources\StudentSessions\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\StudentSessions\StudentSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudentSession extends EditRecord
{
    use SavesSchoolRecord;

    protected static string $resource = StudentSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveSchoolRecord($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
