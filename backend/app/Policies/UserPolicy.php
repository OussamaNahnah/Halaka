<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends SchoolPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user) && $record->roles()->where('name', 'Administrator')->exists();
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->view($user, $record) && ! $user->is($record);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->view($user, $record);
    }
}
