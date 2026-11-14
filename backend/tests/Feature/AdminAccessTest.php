<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_arabic_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('حلقة')
            ->assertSee('Tajawal');
    }

    public function test_only_administrators_can_open_the_panel_even_locally(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $this->actingAs($admin)->get('/admin')->assertOk();

        foreach (['Teacher', 'Student'] as $roleName) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
            $this->actingAs($user)->get('/admin')->assertForbidden();
        }

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_the_default_administrator_can_sign_in(): void
    {
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@admin.com', 'password' => '123456789'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs(User::where('email', 'admin@admin.com')->firstOrFail());
    }

    public function test_a_teacher_cannot_sign_in_to_the_administrator_panel(): void
    {
        $user = User::factory()->create(['password' => 'teacher-password']);
        $role = Role::create(['name' => 'Teacher']);
        $user->roles()->attach($role);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'teacher-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_seeding_is_idempotent_and_preserves_an_existing_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $this->assertTrue(Hash::check('123456789', $admin->password));
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $admin->update(['password' => 'changed-password']);
        $hash = $admin->fresh()->password;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(3, Role::count());
        $this->assertSame(1, $admin->roles()->count());
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_default_credentials_are_not_seeded_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'admin@admin.com']);
    }
}
