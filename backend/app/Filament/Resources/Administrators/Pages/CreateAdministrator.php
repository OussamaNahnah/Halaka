<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Actions\SaveAdministrator;
use App\Filament\Resources\Administrators\AdministratorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdministrator extends CreateRecord
{
    protected static string $resource = AdministratorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveAdministrator::class)->execute($data);
    }
}
