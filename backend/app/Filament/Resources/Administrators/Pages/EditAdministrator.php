<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Actions\SaveAdministrator;
use App\Filament\Resources\Administrators\AdministratorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdministrator extends EditRecord
{
    protected static string $resource = AdministratorResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = '';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveAdministrator::class)->execute($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }
}
