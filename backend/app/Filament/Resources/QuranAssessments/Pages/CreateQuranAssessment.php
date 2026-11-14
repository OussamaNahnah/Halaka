<?php

namespace App\Filament\Resources\QuranAssessments\Pages;

use App\Filament\Concerns\SavesSchoolRecord;
use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuranAssessment extends CreateRecord
{
    use SavesSchoolRecord;

    protected static string $resource = QuranAssessmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveSchoolRecord($data);
    }
}
