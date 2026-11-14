<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveTeacher
{
    public function execute(array $data, ?Teacher $teacher = null): Teacher
    {
        $teacher ? Gate::authorize('update', $teacher) : Gate::authorize('create', Teacher::class);
        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher?->user_id)],
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($teacher, $values) {
            $user = $teacher ? $teacher->user()->lockForUpdate()->firstOrFail() : new User;
            $user->name = $values['name'];
            $user->email = $values['email'];
            if (filled($values['password'] ?? null)) {
                $user->password = $values['password'];
            }
            $user->save();
            $role = Role::firstOrCreate(['name' => 'Teacher']);
            $user->roles()->syncWithoutDetaching([$role->id]);

            return $teacher ?? $user->teacher()->create();
        });
    }
}
