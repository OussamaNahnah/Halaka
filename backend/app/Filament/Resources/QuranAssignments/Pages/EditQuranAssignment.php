<?php

namespace App\Filament\Resources\QuranAssignments\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuranAssignment extends EditRecord
{
    use SavesSchoolRecord;

    protected static string $resource = QuranAssignmentResource::class;

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
