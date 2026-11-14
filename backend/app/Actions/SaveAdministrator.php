<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveAdministrator
{
    public function execute(array $data, ?User $administrator = null): User
    {
        $administrator ? Gate::authorize('update', $administrator) : Gate::authorize('create', User::class);
        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($administrator?->id)],
            'password' => [$administrator ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($values, $administrator) {
            $user = $administrator ? User::withTrashed()->lockForUpdate()->findOrFail($administrator->id) : new User;
            $user->name = $values['name'];
            $user->email = $values['email'];
            if (filled($values['password'] ?? null)) {
                $user->password = $values['password'];
            }
            $user->save();
            $role = Role::firstOrCreate(['name' => 'Administrator']);
            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });
    }
}
