<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        DB::transaction(function () {
            $role = Role::firstOrCreate(['name' => 'Administrator']);
            $admin = User::withTrashed()->firstOrCreate(
                ['email' => 'admin@admin.com'],
                ['name' => 'المشرف', 'password' => Hash::make('123456789')],
            );

            $admin->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
