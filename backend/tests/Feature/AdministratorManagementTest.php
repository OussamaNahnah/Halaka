<?php

namespace Tests\Feature;

use App\Actions\SaveAdministrator;
use App\Actions\SaveTeacher;
use App\Filament\Resources\Administrators\Pages\CreateAdministrator;
use App\Filament\Resources\Administrators\Pages\EditAdministrator;
use App\Filament\Resources\Administrators\Pages\ListAdministrators;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@admin.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function anotherAdministrator(): User
    {
        return app(SaveAdministrator::class)->execute(['name' => 'Second admin', 'email' => 'second@example.test', 'password' => 'initial-password']);
    }

    public function test_administrators_have_a_dashboard_section_and_can_create_accounts(): void
    {
        $this->get('/admin/administrators')->assertOk()->assertSee('المشرفون');
        $this->get('/admin/administrators/create')->assertOk();
        Livewire::test(CreateAdministrator::class)->fillForm(['name' => 'New administrator', 'email' => 'new@example.test', 'password' => 'initial-password'])->call('create')->assertHasNoFormErrors();
        $admin = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('initial-password', $admin->password));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $this->flushSession();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_editing_admins_preserves_blank_passwords_and_existing_roles(): void
    {
        $other = $this->anotherAdministrator();
        $other->roles()->attach(Role::where('name', 'Teacher')->firstOrFail());
        $hash = $other->password;
        Livewire::test(EditAdministrator::class, ['record' => $other->id])
            ->assertFormSet(['password' => ''])
            ->fillForm(['name' => 'Updated', 'password' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $other->fresh()->password);
        $this->assertSame(2, $other->roles()->count());
        Livewire::test(EditAdministrator::class, ['record' => $other->id])->fillForm(['password' => 'reset-password'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('reset-password', $other->fresh()->password));
        $this->assertSame(2, $other->roles()->count());
    }

    public function test_current_admin_can_edit_itself_but_cannot_delete_itself(): void
    {
        $self = auth()->user();
        Livewire::test(EditAdministrator::class, ['record' => $self->id])->fillForm(['name' => 'My name'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('My name', $self->fresh()->name);
        $this->assertFalse(Gate::allows('delete', $self));
        Livewire::test(ListAdministrators::class)->assertActionHidden(TestAction::make('delete')->table($self));
        Livewire::test(EditAdministrator::class, ['record' => $self->id])->assertActionHidden('delete');
        $component = Livewire::test(ListAdministrators::class);
        $component->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $self->id])->call('callMountedAction');
        $this->assertNotSoftDeleted($self);
    }

    public function test_self_deletion_is_also_blocked_at_the_model_boundary(): void
    {
        $self = auth()->user();
        try {
            $self->delete();
            $this->fail('Self-deletion should be denied.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted($self);
        }
    }

    public function test_bulk_deletion_skips_the_logged_in_user_but_deletes_other_admins(): void
    {
        $self = auth()->user();
        $other = $this->anotherAdministrator();
        Livewire::test(ListAdministrators::class)->selectTableRecords([$self, $other])->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertNotSoftDeleted($self);
        $this->assertSoftDeleted($other);
        $this->assertTrue($self->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_other_admins_can_be_archived_and_restored_without_losing_account_data(): void
    {
        $other = $this->anotherAdministrator();
        $hash = $other->password;
        Livewire::test(ListAdministrators::class)->callAction(TestAction::make('delete')->table($other))->assertHasNoActionErrors();
        $this->assertSoftDeleted($other);
        $this->assertFalse($other->fresh()->canAccessPanel(Filament::getPanel('admin')));
        Livewire::test(ListAdministrators::class)->assertCanNotSeeTableRecords([$other])->filterTable('trashed', false)->assertCanSeeTableRecords([$other])->callAction(TestAction::make('restore')->table($other))->assertHasNoActionErrors();
        $this->assertNotSoftDeleted($other);
        $this->assertSame($hash, $other->fresh()->password);
        $this->assertTrue($other->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_archived_administrators_cannot_log_in(): void
    {
        $other = $this->anotherAdministrator();
        $other->delete();
        Auth::logout();
        Livewire::test(Login::class)->fillForm(['email' => $other->email, 'password' => 'initial-password'])->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_teacher_accounts_are_not_listed_or_editable_as_administrators(): void
    {
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Omar', 'email' => 'omar@example.test', 'password' => 'teacher-password']);
        Livewire::test(ListAdministrators::class)->assertCanNotSeeTableRecords([$teacher->user]);
        $this->get('/admin/administrators/'.$teacher->user_id.'/edit')->assertNotFound();
        Livewire::test(CreateAdministrator::class)->fillForm(['name' => 'Duplicate', 'email' => $teacher->user->email, 'password' => 'initial-password'])->call('create')->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_admin_email_uniqueness_also_includes_archived_accounts(): void
    {
        $other = $this->anotherAdministrator();
        $other->delete();
        Livewire::test(CreateAdministrator::class)->fillForm(['name' => 'Duplicate', 'email' => $other->email, 'password' => 'initial-password'])->call('create')->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_reseeding_does_not_resurrect_an_archived_default_admin(): void
    {
        $original = auth()->user();
        $other = $this->anotherAdministrator();
        $this->actingAs($other);
        $original->delete();
        $this->seed(DatabaseSeeder::class);
        $this->assertSoftDeleted($original);
        $this->assertSame(2, User::withTrashed()->count());
    }

    public function test_non_admins_cannot_manage_admins_and_hard_deletion_is_not_allowed(): void
    {
        $self = auth()->user();
        $this->assertFalse(Gate::allows('forceDelete', $self));
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Omar', 'email' => 'omar@example.test', 'password' => 'teacher-password']);
        $this->actingAs($teacher->user);
        $this->get('/admin/administrators')->assertForbidden();
        $this->get('/admin/administrators/create')->assertForbidden();
        $this->assertFalse(Gate::allows('delete', $self));
        $this->assertFalse(Gate::allows('restore', $self));
        $this->expectException(AuthorizationException::class);
        app(SaveAdministrator::class)->execute(['name' => 'Denied', 'email' => 'denied@example.test', 'password' => 'initial-password']);
    }
}
