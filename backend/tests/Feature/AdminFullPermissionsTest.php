<?php

namespace Tests\Feature;

use App\Actions\SaveAssessment;
use App\Actions\SaveTeacher;
use App\Filament\RelationManagers\AssessmentsRelationManager;
use App\Filament\RelationManagers\StudentSessionsRelationManager;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\TeachingSessions\Pages\CreateTeachingSession;
use App\Filament\Resources\TeachingSessions\Pages\EditTeachingSession;
use App\Filament\Resources\TeachingSessions\Pages\ListTeachingSessions;
use App\Filament\Resources\TeachingSessions\Pages\ViewTeachingSession;
use App\Models\Group;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminFullPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@admin.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function records(): array
    {
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Omar', 'email' => 'omar@example.test', 'password' => 'teacher-password']);
        $group = Group::create(['name' => 'Morning']);
        $student = Student::create(['name' => 'Yusuf']);
        $student->groups()->attach($group);
        $session = $teacher->sessions()->create(['timestamp' => now(), 'group_id' => $group->id]);
        $roster = $session->studentSessions()->create(['student_id' => $student->id]);
        $assignment = $student->assignments()->create(['surah_name' => 'Al-Mulk', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'new_memorization']);
        $assessment = $roster->assessments()->create(['quran_assignment_id' => $assignment->id, 'grade' => 7.25, 'comment' => 'Preserve history']);

        return compact('teacher', 'group', 'student', 'session', 'roster', 'assignment', 'assessment');
    }

    public function test_admin_creates_and_edits_sessions_without_rebuilding_saved_rosters(): void
    {
        extract($this->records());
        $other = Student::create(['name' => 'Maryam']);
        Livewire::test(CreateTeachingSession::class)->fillForm([
            'teacher_id' => $teacher->id, 'group_id' => null, 'timestamp' => '2026-11-15 10:00:00', 'status' => 'in_progress', 'student_ids' => [$student->id, $other->id],
        ])->call('create')->assertHasNoFormErrors();
        $created = TeachingSession::latest('id')->firstOrFail();
        $this->assertSame(2, $created->studentSessions()->count());
        $this->assertNull($created->group_id);
        Livewire::test(EditTeachingSession::class, ['record' => $session->id])->fillForm(['group_id' => null, 'status' => 'completed'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($session->fresh()->group_id);
        $this->assertSame($roster->id, $session->studentSessions()->sole()->id);
        $this->assertSame('absent', $roster->fresh()->attendance_status);
        $this->assertDatabaseHas('quran_assessments', ['id' => $assessment->id, 'grade' => 7.25]);
    }

    public function test_admin_manages_attendance_from_a_session_and_student_history(): void
    {
        extract($this->records());
        Livewire::test(StudentSessionsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->callAction(TestAction::make('edit')->table($roster), data: ['student_id' => $student->id, 'attendance_status' => 'present', 'evaluated_at' => null])
            ->assertHasNoActionErrors();
        $this->assertSame('present', $roster->fresh()->attendance_status);
        $this->assertNotNull($roster->fresh()->evaluated_at);
        Livewire::test(StudentSessionsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->callAction(TestAction::make('edit')->table($roster), data: ['session_id' => $session->id, 'attendance_status' => 'absent', 'evaluated_at' => null])
            ->assertHasNoActionErrors();
        $this->assertSame('absent', $roster->fresh()->attendance_status);
        $this->assertNull($roster->fresh()->evaluated_at);
        $other = Student::create(['name' => 'Maryam']);
        Livewire::test(StudentSessionsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->callAction(TestAction::make('create')->table(), data: ['student_id' => $other->id, 'attendance_status' => 'present'])
            ->assertHasNoActionErrors();
        $this->assertSame(2, $session->studentSessions()->count());
    }

    public function test_admin_creates_and_changes_assignments_and_passing_state(): void
    {
        extract($this->records());
        $component = Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class]);
        $component->callAction(TestAction::make('create')->table(), data: ['surah_name' => 'An-Nas', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision', 'is_passed' => false])->assertHasNoActionErrors();
        $this->assertSame(2, $student->assignments()->count());
        $component->callAction(TestAction::make('edit')->table($assignment), data: ['surah_name' => 'Al-Mulk', 'from_ayah' => 2, 'to_ayah' => 8, 'type' => 'revision', 'is_passed' => true])->assertHasNoActionErrors();
        $this->assertTrue($assignment->fresh()->is_passed);
        $this->assertSame(2, $assignment->fresh()->from_ayah);
        $this->assertEquals(7.25, $assessment->fresh()->grade);
    }

    public function test_assignment_forms_reject_out_of_surah_ranges(): void
    {
        extract($this->records());
        Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->callAction(TestAction::make('create')->table(), data: ['surah_name' => 'An-Nas', 'from_ayah' => 1, 'to_ayah' => 7, 'type' => 'revision', 'is_passed' => false])
            ->assertHasActionErrors(['to_ayah']);
        $this->assertSame(1, QuranAssignment::count());
    }

    public function test_admin_creates_and_edits_assessments_from_both_parent_views(): void
    {
        extract($this->records());
        Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->callAction(TestAction::make('create')->table(), data: ['student_session_id' => $roster->id, 'quran_assignment_id' => $assignment->id, 'grade' => 23.125, 'comment' => 'New attempt'])->assertHasNoActionErrors();
        $this->assertSame(2, $assignment->assessments()->count());
        $created = QuranAssessment::latest('id')->firstOrFail();
        Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->callAction(TestAction::make('edit')->table($created), data: ['student_session_id' => $roster->id, 'quran_assignment_id' => $assignment->id, 'grade' => -7.5, 'comment' => 'Edited'])->assertHasNoActionErrors();
        $this->assertEquals(-7.5, $created->fresh()->grade);
        $this->assertEquals(7.25, $assessment->fresh()->grade);
    }

    public function test_assessments_cannot_point_to_another_students_assignment(): void
    {
        extract($this->records());
        $other = Student::create(['name' => 'Other']);
        $otherAssignment = $other->assignments()->create(['surah_name' => 'An-Nas', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision']);
        Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->callAction(TestAction::make('create')->table(), data: ['student_session_id' => $roster->id, 'quran_assignment_id' => $otherAssignment->id, 'grade' => 8])
            ->assertHasActionErrors(['quran_assignment_id']);
        $this->assertSame(1, QuranAssessment::count());
        $this->expectException(ValidationException::class);
        app(SaveAssessment::class)->execute($session, ['student_session_id' => $roster->id, 'quran_assignment_id' => $otherAssignment->id, 'grade' => 8]);
    }

    public function test_parent_archiving_keeps_history_and_shared_accounts_and_can_be_restored(): void
    {
        extract($this->records());
        $accountId = $teacher->user_id;
        foreach ([[$student, ListStudents::class], [$group, ListGroups::class], [$teacher, ListTeachers::class], [$session, ListTeachingSessions::class]] as [$record, $page]) {
            Livewire::test($page)->callAction(TestAction::make('delete')->table($record))->assertHasNoActionErrors();
            $this->assertSoftDeleted($record);
        }
        $this->assertSame(1, StudentSession::count());
        $this->assertSame(1, QuranAssignment::count());
        $this->assertSame(1, QuranAssessment::count());
        $this->assertSame('Yusuf', $assessment->fresh()->assignment->student->name);
        $this->assertSame('Omar', $assessment->fresh()->studentSession->session->teacher->user->name);
        $this->assertDatabaseHas('users', ['id' => $accountId]);
        $this->assertDatabaseHas('group_student', ['group_id' => $group->id, 'student_id' => $student->id]);
        foreach ([[$student, ListStudents::class], [$group, ListGroups::class], [$teacher, ListTeachers::class], [$session, ListTeachingSessions::class]] as [$record, $page]) {
            Livewire::test($page)->filterTable('trashed', false)->assertCanSeeTableRecords([$record])->callAction(TestAction::make('restore')->table($record))->assertHasNoActionErrors();
            $this->assertNotSoftDeleted($record);
        }
        $this->assertSame(1, $student->groups()->count());
    }

    public function test_nested_soft_delete_restore_preserves_all_original_keys(): void
    {
        extract($this->records());
        foreach ([[$assessment, AssessmentsRelationManager::class], [$assignment, AssignmentsRelationManager::class], [$roster, StudentSessionsRelationManager::class]] as [$record, $manager]) {
            Livewire::test($manager, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])->callAction(TestAction::make('delete')->table($record))->assertHasNoActionErrors();
            $this->assertSoftDeleted($record);
            Livewire::test($manager, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])->filterTable('trashed', false)->callAction(TestAction::make('restore')->table($record))->assertHasNoActionErrors();
            $this->assertNotSoftDeleted($record);
        }
        $this->assertSame($assignment->id, $assessment->fresh()->quran_assignment_id);
        $this->assertSame($roster->id, $assessment->fresh()->student_session_id);
    }

    public function test_archived_intermediate_records_do_not_hide_assessment_history(): void
    {
        extract($this->records());
        $assignment->delete();
        $roster->delete();
        $this->assertSame(1, $student->assessments()->count());
        $this->assertSame(1, $session->assessments()->count());
        Livewire::test(AssessmentsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])->assertCanSeeTableRecords([$assessment]);
    }

    public function test_restoration_does_not_need_duplicate_roster_records(): void
    {
        extract($this->records());
        $roster->delete();
        Livewire::test(StudentSessionsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewTeachingSession::class])
            ->callAction(TestAction::make('create')->table(), data: ['student_id' => $student->id, 'attendance_status' => 'present'])
            ->assertHasActionErrors(['student_id']);
        $this->assertSame(1, StudentSession::withTrashed()->count());
    }

    public function test_all_management_and_restore_permissions_are_admin_only_without_hard_deletion(): void
    {
        extract($this->records());
        foreach ([$student, $group, $teacher, $session, $roster, $assignment, $assessment] as $record) {
            foreach (['view', 'update', 'delete', 'restore'] as $ability) {
                $this->assertTrue(Gate::allows($ability, $record));
            }
            $this->assertFalse(Gate::allows('forceDelete', $record));
        }
        $this->actingAs($teacher->user);
        foreach ([$student, $group, $teacher, $session, $roster, $assignment, $assessment] as $record) {
            foreach (['create', 'viewAny', 'restoreAny'] as $ability) {
                $this->assertFalse(Gate::allows($ability, $record::class));
            }
            foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
                $this->assertFalse(Gate::allows($ability, $record));
            }
        }
    }

    public function test_editing_memberships_keeps_links_to_archived_students_and_groups(): void
    {
        extract($this->records());
        $group->delete();
        Livewire::test(EditStudent::class, ['record' => $student->id])
            ->assertFormSet(['groups' => [$group->id]])
            ->fillForm(['name' => 'Updated name'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseHas('group_student', ['group_id' => $group->id, 'student_id' => $student->id]);
        $group->restore();
        $student->delete();
        Livewire::test(EditGroup::class, ['record' => $group->id])
            ->assertFormSet(['students' => [$student->id]])
            ->fillForm(['name' => 'Updated group'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseHas('group_student', ['group_id' => $group->id, 'student_id' => $student->id]);
    }
}
