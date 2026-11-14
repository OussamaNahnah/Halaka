<?php

namespace App\Filament\Resources\StudentSessions\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\StudentSessions\StudentSessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudentSession extends CreateRecord
{
    use SavesSchoolRecord;

    protected static string $resource = StudentSessionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveSchoolRecord($data);
    }
}
