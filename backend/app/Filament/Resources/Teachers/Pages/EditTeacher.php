<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Actions\SaveTeacher;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getRecord()->user;

        return ['name' => $user->name, 'email' => $user->email, 'password' => ''];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveTeacher::class)->execute($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }
}
