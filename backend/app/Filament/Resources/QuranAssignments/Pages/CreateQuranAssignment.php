<?php

namespace App\Filament\Resources\QuranAssignments\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuranAssignment extends CreateRecord
{
    use SavesSchoolRecord;

    protected static string $resource = QuranAssignmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveSchoolRecord($data);
    }
}
