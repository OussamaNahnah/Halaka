<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait SavesSchoolRecord
{
    protected function saveSchoolRecord(array $data, ?Model $record = null): Model
    {
        try {
            return static::getResource()::saveRecord($data, $record);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$this->form->getStatePath().'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
