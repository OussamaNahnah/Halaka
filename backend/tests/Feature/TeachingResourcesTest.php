<?php

namespace Tests\Feature;

use App\Actions\SaveTeacher;
use App\Filament\RelationManagers\CreatedAssignmentsRelationManager;
use App\Filament\Resources\QuranAssessments\Pages\CreateQuranAssessment;
use App\Filament\Resources\QuranAssessments\Pages\EditQuranAssessment;
use App\Filament\Resources\QuranAssessments\Pages\ListQuranAssessments;
use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use App\Filament\Resources\QuranAssignments\Pages\CreateQuranAssignment;
use App\Filament\Resources\QuranAssignments\Pages\EditQuranAssignment;
use App\Filament\Resources\QuranAssignments\Pages\ListQuranAssignments;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\StudentSessions\Pages\CreateStudentSession;
use App\Filament\Resources\StudentSessions\Pages\EditStudentSession;
use App\Filament\Resources\StudentSessions\Pages\ListStudentSessions;
use App\Filament\Resources\StudentSessions\StudentSessionResource;
use App\Filament\Resources\TeachingSessions\Pages\ViewTeachingSession;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeachingResourcesTest extends TestCase
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
        $teacher = app(SaveTeacher::class)->execute(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password123']);
        $student = Student::create(['name' => 'Yusuf']);
        $session = $teacher->sessions()->create(['timestamp' => now()]);
        $roster = $session->studentSessions()->create(['student_id' => $student->id]);
        $assignment = $student->assignments()->create(['surah_name' => 'الناس', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision']);
        $assessment = $roster->assessments()->create(['quran_assignment_id' => $assignment->id, 'grade' => 8.125]);

        return compact('teacher', 'student', 'session', 'roster', 'assignment', 'assessment');
    }

    public function test_all_standalone_pages_render_with_related_records_and_admin_only_navigation(): void
    {
        extract($this->records());
        foreach ([[StudentSessionResource::class, $roster], [QuranAssignmentResource::class, $assignment], [QuranAssessmentResource::class, $assessment]] as [$resource,$record]) {
            $this->assertSame('التعليم', $resource::getNavigationGroup());
            foreach (['index', 'create', 'edit', 'view'] as $page) {
                $this->get($resource::getUrl($page, in_array($page, ['edit', 'view']) ? ['record' => $record->id] : []))->assertOk();
            }
        }
        $this->actingAs($teacher->user);
        $this->flushSession();
        foreach ([StudentSessionResource::class, QuranAssignmentResource::class, QuranAssessmentResource::class] as $resource) {
            $this->get($resource::getUrl())->assertForbidden();
        }
    }

    public function test_rosters_can_be_created_edited_and_duplicates_are_rejected(): void
    {
        extract($this->records());
        $other = Student::create(['name' => 'Maryam']);
        Livewire::test(CreateStudentSession::class)->fillForm(['student_id' => $other->id, 'session_id' => $session->id, 'attendance_status' => 'present'])->call('create')->assertHasNoFormErrors();
        $created = StudentSession::latest('id')->firstOrFail();
        $this->assertNotNull($created->evaluated_at);
        Livewire::test(EditStudentSession::class, ['record' => $created->id])->fillForm(['attendance_status' => 'absent'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($created->fresh()->evaluated_at);
        $roster->delete();
        Livewire::test(CreateStudentSession::class)->fillForm(['student_id' => $student->id, 'session_id' => $session->id, 'attendance_status' => 'present'])->call('create')->assertHasFormErrors(['student_id']);
    }

    public function test_assignments_can_be_created_edited_and_student_history_cannot_be_mismatched(): void
    {
        extract($this->records());
        Livewire::test(CreateQuranAssignment::class)->fillForm(['student_id' => $student->id, 'surah_name' => 'الناس', 'from_ayah' => 1, 'to_ayah' => 3, 'type' => 'revision', 'is_passed' => false])->call('create')->assertHasNoFormErrors();
        $created = QuranAssignment::latest('id')->firstOrFail();
        $other = Student::create(['name' => 'Maryam']);
        Livewire::test(EditQuranAssignment::class, ['record' => $created->id])->fillForm(['student_id' => $other->id, 'is_passed' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame($other->id, $created->fresh()->student_id);
        $this->assertTrue($created->fresh()->is_passed);
        Livewire::test(EditQuranAssignment::class, ['record' => $assignment->id])->fillForm(['student_id' => $other->id])->call('save')->assertHasFormErrors(['student_id']);
        $this->assertSame($student->id, $assignment->fresh()->student_id);
    }

    public function test_assessments_allow_repeated_free_numeric_grades_and_reject_wrong_student(): void
    {
        extract($this->records());
        Livewire::test(CreateQuranAssessment::class)->fillForm(['student_session_id' => $roster->id, 'quran_assignment_id' => $assignment->id, 'grade' => -15.125, 'comment' => 'Repeat'])->call('create')->assertHasNoFormErrors();
        $created = QuranAssessment::latest('id')->firstOrFail();
        $this->assertEquals(-15.125, $created->grade);
        Livewire::test(EditQuranAssessment::class, ['record' => $created->id])->fillForm(['grade' => 120])->call('save')->assertHasNoFormErrors();
        $this->assertEquals(120, $created->fresh()->grade);
        $other = Student::create(['name' => 'Maryam']);
        $wrong = $other->assignments()->create(['surah_name' => 'الناس', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision']);
        Livewire::test(CreateQuranAssessment::class)->fillForm(['student_session_id' => $roster->id, 'quran_assignment_id' => $wrong->id, 'grade' => 8])->call('create')->assertHasFormErrors(['quran_assignment_id']);
        $this->assertSame(2, QuranAssessment::count());
    }

    public function test_soft_delete_restore_bulk_actions_and_filters_keep_linked_history(): void
    {
        extract($this->records());
        Livewire::test(ListQuranAssessments::class)->filterTable('quran_assignment_id', $assignment->id)->assertCanSeeTableRecords([$assessment]);
        Livewire::test(ListQuranAssessments::class)->filterTable('student_session_id', $roster->id)->assertCanSeeTableRecords([$assessment]);
        foreach ([[$roster, ListStudentSessions::class], [$assignment, ListQuranAssignments::class], [$assessment, ListQuranAssessments::class]] as [$record,$page]) {
            Livewire::test($page)->callAction(TestAction::make('delete')->table($record))->assertHasNoActionErrors();
            $this->assertSoftDeleted($record);
            Livewire::test($page)->filterTable('trashed', false)->assertCanSeeTableRecords([$record])->selectTableRecords([$record->id])->callAction(TestAction::make('restore')->table()->bulk())->assertHasNoActionErrors();
            $this->assertNotSoftDeleted($record);
        }
        $this->assertSame($assignment->id, $assessment->fresh()->quran_assignment_id);
        $this->assertSame($roster->id, $assessment->fresh()->student_session_id);
    }

    public function test_assignment_origin_is_linked_read_only_and_listed_under_its_session(): void
    {
        extract($this->records());
        $assignment->forceFill(['created_in_session_id' => $session->id])->save();
        $sessionUrl = TeachingSessionResource::getUrl('view', ['record' => $session->id]);
        $this->get(QuranAssignmentResource::getUrl('view', ['record' => $assignment->id]))->assertOk()->assertSee('حصة إنشاء الواجب')->assertSee($sessionUrl, false);
        $this->get(QuranAssignmentResource::getUrl('edit', ['record' => $assignment->id]))->assertOk()->assertSee('حصة إنشاء الواجب');
        Livewire::test(ListQuranAssignments::class)->assertCanSeeTableRecords([$assignment])->assertSee('حصة الإنشاء')->assertSee($sessionUrl, false);
        Livewire::test(EditQuranAssignment::class, ['record' => $assignment->id])->fillForm(['created_in_session_id' => null, 'is_passed' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame($session->id, $assignment->fresh()->created_in_session_id);
        $this->assertTrue($assignment->fresh()->is_passed);
        Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])->assertCanSeeTableRecords([$assignment])->assertSee('حصة الإنشاء');
        $manager = CreatedAssignmentsRelationManager::class;
        $page = ViewTeachingSession::class;
        Livewire::test($manager, ['ownerRecord' => $session, 'pageClass' => $page])->assertCanSeeTableRecords([$assignment]);
        $legacy = $student->assignments()->create(['surah_name' => 'الملك', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'revision']);
        Livewire::test($manager, ['ownerRecord' => $session, 'pageClass' => $page])->assertCanNotSeeTableRecords([$legacy]);
        $session->delete();
        $this->get(QuranAssignmentResource::getUrl('view', ['record' => $assignment->id]))->assertOk()->assertSee($sessionUrl, false);
        $this->get($sessionUrl)->assertOk();
        $this->assertSame($session->id, $assignment->fresh()->createdInSession->id);
    }
}
