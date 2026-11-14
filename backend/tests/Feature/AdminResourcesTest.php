<?php

namespace Tests\Feature;

use App\Actions\CorrectAssessment;
use App\Actions\SaveTeacher;
use App\Filament\RelationManagers\AssessmentsRelationManager;
use App\Filament\RelationManagers\StudentSessionsRelationManager;
use App\Filament\Resources\Groups\Pages\CreateGroup;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\TeachingSessions\Pages\ViewTeachingSession;
use App\Models\Group;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@admin.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function classroom(): array
    {
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Teacher Omar', 'email' => 'teacher@example.test', 'password' => 'teacher-password']);
        $group = Group::create(['name' => 'Morning']);
        $student = Student::create(['name' => 'Yusuf']);
        $group->students()->attach($student);
        $session = $teacher->sessions()->create(['group_id' => $group->id, 'timestamp' => now(), 'status' => 'completed']);
        $roster = $session->studentSessions()->create(['student_id' => $student->id, 'attendance_status' => 'present', 'evaluated_at' => now()]);
        $assignment = $student->assignments()->create(['surah_name' => 'Al-Mulk', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'new_memorization']);
        $assessment = $roster->assessments()->create(['quran_assignment_id' => $assignment->id, 'grade' => 0, 'comment' => 'First attempt']);

        return compact('teacher', 'group', 'student', 'session', 'roster', 'assignment', 'assessment');
    }

    public function test_all_resource_pages_render_and_students_have_linked_history(): void
    {
        extract($this->classroom());
        foreach (['students', 'groups', 'teachers', 'teaching-sessions'] as $slug) {
            $this->get('/admin/'.$slug)->assertOk();
        }
        foreach (['students', 'groups', 'teachers'] as $slug) {
            $this->get('/admin/'.$slug.'/create')->assertOk();
        }
        $this->get('/admin/students/'.$student->id)->assertOk()->assertSee('Yusuf');
        $this->get('/admin/students/'.$student->id.'/edit')->assertOk();
        $this->get('/admin/groups/'.$group->id.'/edit')->assertOk();
        $this->get('/admin/teachers/'.$teacher->id.'/edit')->assertOk();
        $this->get('/admin/teaching-sessions/'.$session->id)->assertOk()->assertSee('Teacher Omar');
        $this->get('/admin/teaching-sessions/create')->assertOk();
        $this->get('/admin/teaching-sessions/'.$session->id.'/edit')->assertOk();
    }

    public function test_teacher_forms_create_accounts_and_preserve_blank_passwords(): void
    {
        Livewire::test(CreateTeacher::class)->fillForm(['name' => 'Omar', 'email' => 'omar@example.test', 'password' => 'initial-password'])->call('create')->assertHasNoFormErrors();
        $teacher = Teacher::with('user')->sole();
        $this->assertTrue(Hash::check('initial-password', $teacher->user->password));
        $this->assertTrue($teacher->user->roles()->where('name', 'Teacher')->exists());
        $hash = $teacher->user->password;
        Livewire::test(EditTeacher::class, ['record' => $teacher->id])
            ->assertFormSet(['name' => 'Omar', 'email' => 'omar@example.test', 'password' => ''])
            ->fillForm(['name' => 'Omar Updated', 'password' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $teacher->user->fresh()->password);
        Livewire::test(EditTeacher::class, ['record' => $teacher->id])->fillForm(['password' => 'reset-password'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('reset-password', $teacher->user->fresh()->password));
    }

    public function test_teacher_creation_requires_password_and_globally_unique_email(): void
    {
        Livewire::test(CreateTeacher::class)->fillForm(['name' => 'Omar', 'email' => 'omar@example.test'])->call('create')->assertHasFormErrors(['password' => 'required']);
        Livewire::test(CreateTeacher::class)->fillForm(['name' => 'Omar', 'email' => 'admin@admin.com', 'password' => 'initial-password'])->call('create')->assertHasFormErrors(['email' => 'unique']);
        $this->assertSame(0, Teacher::count());
        $this->assertSame(1, User::count());
    }

    public function test_account_creation_rolls_back_if_the_teacher_profile_cannot_be_saved(): void
    {
        Teacher::creating(fn () => throw new \RuntimeException('Profile failure'));
        try {
            app(SaveTeacher::class)->execute(['name' => 'Omar', 'email' => 'rollback@example.test', 'password' => 'initial-password']);
            $this->fail('Expected profile creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Profile failure', $exception->getMessage());
        } finally {
            Teacher::flushEventListeners();
        }
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertSame(0, Teacher::count());
    }

    public function test_membership_forms_use_the_same_pivot_and_preserve_historical_rosters(): void
    {
        extract($this->classroom());
        $other = Group::create(['name' => 'Evening']);
        Livewire::test(EditStudent::class, ['record' => $student->id])->fillForm(['groups' => [$other->id]])->call('save')->assertHasNoFormErrors();
        $this->assertSame([$other->id], $student->groups()->pluck('groups.id')->all());
        $this->assertDatabaseHas('student_sessions', ['id' => $roster->id, 'session_id' => $session->id]);
        Livewire::test(EditGroup::class, ['record' => $group->id])->fillForm(['students' => [$student->id]])->call('save')->assertHasNoFormErrors();
        $this->assertCount(2, $student->groups()->get());
        Livewire::test(EditGroup::class, ['record' => $group->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame(1, $group->students()->count());
    }

    public function test_student_and_group_creation_allow_optional_and_shared_parent_phone(): void
    {
        Livewire::test(CreateStudent::class)->fillForm(['name' => 'Amina', 'parent_phone' => null])->call('create')->assertHasNoFormErrors();
        foreach (['Yusuf', 'Maryam'] as $name) {
            Livewire::test(CreateStudent::class)->fillForm(['name' => $name, 'parent_phone' => '+213555555555'])->call('create')->assertHasNoFormErrors();
        }
        $this->assertSame(3, Student::count());
        $this->assertSame(1, User::count());
        Livewire::test(CreateGroup::class)->fillForm(['name' => 'Morning', 'students' => Student::pluck('id')->all()])->call('create')->assertHasNoFormErrors();
        $this->assertSame(3, Group::sole()->students()->count());
    }

    public function test_student_assignment_history_keeps_pending_attempts_and_shows_all_grades(): void
    {
        extract($this->classroom());
        $roster->assessments()->create(['quran_assignment_id' => $assignment->id, 'grade' => 12.75, 'comment' => 'Another attempt']);
        $passed = $student->assignments()->create(['surah_name' => 'An-Naba', 'from_ayah' => 1, 'to_ayah' => 3, 'type' => 'revision', 'is_passed' => true]);
        $table = Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class]);
        $table->assertCanSeeTableRecords([$assignment, $passed])
            ->mountAction(TestAction::make('view')->table($assignment))
            ->assertActionMounted(TestAction::make('view')->table($assignment));
        $instance = $table->instance();
        $markup = $instance->getSchema($instance->getMountedActionSchemaName())->toHtml();
        $this->assertStringContainsString('First attempt', $markup);
        $this->assertStringContainsString('Another attempt', $markup);
        $this->assertStringContainsString('12.75', $markup);
        $table->call('mountAction', 'correctAssessment', [], ['recordKey' => (string) $assessment->id, 'schemaComponent' => 'mountedActionSchema0.assessments.0'])
            ->fillForm(['grade' => 17.25, 'comment' => 'Corrected from assignment'])
            ->call('callMountedAction')->assertHasNoActionErrors();
        $this->assertEquals(17.25, $assessment->fresh()->grade);
        $this->assertFalse($assignment->fresh()->is_passed);
        Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->filterTable('is_passed', true)->assertCanSeeTableRecords([$passed])->assertCanNotSeeTableRecords([$assignment]);
    }

    public function test_assessment_corrections_work_from_student_and_session_views(): void
    {
        extract($this->classroom());
        $links = $assessment->only(['student_session_id', 'quran_assignment_id']);
        foreach ([[$student, ViewStudent::class, -2.125], [$session, ViewTeachingSession::class, 21.75]] as [$owner, $page, $grade]) {
            Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => $page])
                ->assertCanSeeTableRecords([$assessment])
                ->callAction(TestAction::make('correctAssessment')->table($assessment), data: ['grade' => $grade, 'comment' => 'Corrected'])
                ->assertHasNoActionErrors();
            $this->assertEquals($grade, $assessment->fresh()->grade);
            $this->assertSame($links, $assessment->fresh()->only(['student_session_id', 'quran_assignment_id']));
        }
        $this->assertSame(1, QuranAssessment::count());
        $this->assertFalse($assignment->fresh()->is_passed);
    }

    public function test_correction_rejects_missing_and_non_numeric_grades(): void
    {
        extract($this->classroom());
        foreach (['', 'invalid'] as $grade) {
            Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
                ->callAction(TestAction::make('correctAssessment')->table($assessment), data: ['grade' => $grade])
                ->assertHasActionErrors(['grade']);
        }
        $this->assertEquals(0, $assessment->fresh()->grade);
    }

    public function test_roster_links_and_admin_permissions_remain_protected(): void
    {
        extract($this->classroom());
        Livewire::test(StudentSessionsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->assertCanSeeTableRecords([$roster])->assertSee('Yusuf');
        foreach ([$student, $group, $teacher, $session, $assignment, $assessment, $roster] as $record) {
            $this->assertTrue(Gate::allows('delete', $record));
            $this->assertTrue(Gate::allows('restore', $record));
            $this->assertFalse(Gate::allows('forceDelete', $record));
        }
        foreach ([TeachingSession::class, QuranAssignment::class, QuranAssessment::class] as $model) {
            $this->assertTrue(Gate::allows('create', $model));
        }
        $this->assertTrue(Gate::allows('update', $roster));
        $this->assertTrue(Gate::allows('update', $assignment));
        $this->actingAs($teacher->user);
        foreach (['students', 'groups', 'teachers', 'teaching-sessions'] as $slug) {
            $this->get('/admin/'.$slug)->assertForbidden();
        }
        $this->expectException(AuthorizationException::class);
        app(CorrectAssessment::class)->execute($assessment, ['grade' => 100]);
    }
}
