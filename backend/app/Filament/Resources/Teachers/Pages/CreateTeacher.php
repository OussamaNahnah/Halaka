<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Actions\SaveTeacher;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTeacher::class)->execute($data);
    }
}
