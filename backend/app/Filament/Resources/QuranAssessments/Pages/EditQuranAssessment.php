<?php

namespace App\Filament\Resources\QuranAssessments\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuranAssessment extends EditRecord
{
    use SavesSchoolRecord;

    protected static string $resource = QuranAssessmentResource::class;

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
