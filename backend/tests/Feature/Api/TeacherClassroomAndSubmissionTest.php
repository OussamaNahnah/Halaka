<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Models\User;
use App\Support\SchoolSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherClassroomAndSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    private Teacher $other;

    private Group $group;

    private Student $student;

    private Student $absent;

    private QuranAssignment $assignment;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Teacher']);
        $user = User::factory()->create(['email' => 'teacher@example.test', 'password' => 'password123']);
        $user->roles()->attach($role);
        $this->teacher = Teacher::create(['user_id' => $user->id]);
        $other = User::factory()->create();
        $other->roles()->attach($role);
        $this->other = Teacher::create(['user_id' => $other->id]);
        $this->group = Group::create(['name' => 'Morning']);
        $this->student = Student::create(['name' => 'Yusuf', 'parent_phone' => 'private-phone']);
        $this->absent = Student::create(['name' => 'Maryam']);
        $this->group->students()->attach([$this->student->id, $this->absent->id]);
        $this->assignment = $this->student->assignments()->create(['surah_name' => 'An-Nas', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk()->json('access_token');
        $this->headers = ['Authorization' => 'Bearer '.$token];
    }

    private function payload(): array
    {
        return ['submission_id' => (string) Str::uuid(), 'client_uuid' => (string) Str::uuid(), 'base_sync_revision' => SchoolSync::revision(), 'group_id' => $this->group->id, 'timestamp' => '2026-11-14T10:00:00Z', 'status' => 'completed', 'students' => [
            ['student_id' => $this->student->id, 'evaluated_at' => '2026-11-14T10:30:00Z', 'assignments' => [], 'assessments' => [['client_uuid' => (string) Str::uuid(), 'quran_assignment_id' => $this->assignment->id, 'grade' => -12.125, 'comment' => 'First attempt']]],
            ['student_id' => $this->absent->id, 'evaluated_at' => null, 'assignments' => [], 'assessments' => []],
        ]];
    }

    private function upload(array $payload)
    {
        return $this->postJson('/api/sessions', $payload, $this->headers);
    }

    private function revision(array $payload): array
    {
        $payload['submission_id'] = (string) Str::uuid();
        $payload['base_sync_revision'] = SchoolSync::revision();
        $payload['base_revision'] = TeachingSession::where('client_uuid', $payload['client_uuid'])->firstOrFail()->revision;

        return $payload;
    }

    public function test_all_groups_and_students_are_available_but_sessions_belong_to_current_teacher_only(): void
    {
        $second = Group::create(['name' => 'Evening']);
        $own = $this->teacher->sessions()->create(['timestamp' => now()->subDay(), 'group_id' => $this->group->id]);
        $last = $this->teacher->sessions()->create(['timestamp' => now(), 'group_id' => $second->id]);
        $foreign = $this->other->sessions()->create(['timestamp' => now(), 'group_id' => $this->group->id]);
        $this->getJson('/api/groups', $this->headers)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.suggested_group_id', $second->id);
        $this->getJson('/api/groups/'.$this->group->id.'/students', $this->headers)->assertOk()->assertJsonCount(2, 'data')->assertDontSee('private-phone');
        $this->getJson('/api/students/'.$this->student->id.'/assignments', $this->headers)->assertOk()->assertJsonPath('data.0.id', $this->assignment->id);
        $this->getJson('/api/quran/surahs', $this->headers)->assertOk()->assertJsonCount(114, 'data');
        $this->getJson('/api/sessions', $this->headers)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $last->id);
        $this->getJson('/api/sessions/'.$foreign->id, $this->headers)->assertNotFound();
        $this->getJson('/api/sessions/'.$own->id, $this->headers)->assertOk();
        $this->getJson('/api/sessions?per_page=10000', $this->headers)->assertUnprocessable();
    }

    public function test_archived_groups_students_sessions_and_teacher_accounts_are_not_exposed(): void
    {
        $this->group->delete();
        $this->getJson('/api/groups', $this->headers)->assertJsonCount(0, 'data');
        $this->getJson('/api/groups/'.$this->group->id.'/students', $this->headers)->assertNotFound();
        $this->student->delete();
        $this->getJson('/api/students/'.$this->student->id.'/assignments', $this->headers)->assertNotFound();
        $session = $this->teacher->sessions()->create(['timestamp' => now()]);
        $session->delete();
        $this->getJson('/api/sessions/'.$session->id, $this->headers)->assertNotFound();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->teacher->user->delete();
        $this->getJson('/api/profile', $this->headers)->assertUnauthorized();
    }

    public function test_atomic_upload_maps_ids_and_retry_returns_same_acknowledgement(): void
    {
        $p = $this->payload();
        $new = (string) Str::uuid();
        $attempt = (string) Str::uuid();
        $p['students'][0]['assignments'][] = ['client_uuid' => $new, 'surah_name' => 'Al-Mulk', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'new_memorization', 'is_passed' => false];
        $p['students'][0]['assessments'][] = ['client_uuid' => $attempt, 'assignment_client_uuid' => $new, 'grade' => 123.456789];
        $first = $this->upload($p)->assertCreated()->assertJsonPath('data.teacher_id', $this->teacher->id)->assertJsonPath('data.status', 'completed')->assertJsonPath('data.students.0.attendance_status', 'present')->assertJsonPath('data.students.1.attendance_status', 'absent');
        $this->assertEquals(-12.125, QuranAssessment::firstOrFail()->grade);
        $this->assertArrayHasKey($new, $first->json('id_mapping.assignments'));
        $this->assertArrayHasKey($attempt, $first->json('id_mapping.assessments'));
        $this->upload($p)->assertCreated()->assertExactJson($first->json());
        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertDatabaseCount('student_sessions', 2);
        $this->assertDatabaseCount('quran_assessments', 2);
        $this->assertDatabaseCount('teacher_submissions', 1);
        $p['timestamp'] = '2026-11-15T10:00:00Z';
        $this->upload($p)->assertConflict();
    }

    public function test_whole_submission_rolls_back_on_late_student_validation_error(): void
    {
        $p = $this->payload();
        $revision = SchoolSync::revision();
        $p['students'][1]['assessments'][] = ['client_uuid' => (string) Str::uuid(), 'quran_assignment_id' => $this->assignment->id, 'grade' => 7];
        $this->upload($p)->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 0);
        $this->assertDatabaseCount('quran_assessments', 0);
        $this->assertDatabaseCount('teacher_submissions', 0);
        $this->assertSame($revision, SchoolSync::revision());
    }

    public function test_upload_rejects_wrong_student_and_unresolved_assignment_dependencies(): void
    {
        $wrong = $this->absent->assignments()->create(['surah_name' => 'An-Nas', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision']);
        $p = $this->payload();
        $p['students'][0]['assessments'][0]['quran_assignment_id'] = $wrong->id;
        $this->upload($p)->assertUnprocessable();
        $p = $this->payload();
        unset($p['students'][0]['assessments'][0]['quran_assignment_id']);
        $p['students'][0]['assessments'][0]['assignment_client_uuid'] = (string) Str::uuid();
        $this->upload($p)->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 0);
    }

    public function test_update_preserves_attempts_allows_decimal_edits_and_prevents_foreign_session_changes(): void
    {
        $p = $this->payload();
        $id = $this->upload($p)->assertCreated()->json('data.id');
        $p = $this->revision($p);
        $p['students'][0]['assessments'][0]['grade'] = 120.98765;
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertDatabaseCount('quran_assessments', 1);
        $this->assertEquals(120.98765, QuranAssessment::firstOrFail()->grade);
        $p = $this->revision($p);
        $p['students'][0]['assessments'] = [];
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertUnprocessable();
        $this->assertDatabaseCount('quran_assessments', 1);
        $foreign = $this->other->sessions()->create(['timestamp' => now()]);
        $p = $this->revision($p);
        $this->putJson('/api/sessions/'.$foreign->id, $p, $this->headers)->assertNotFound();
    }

    public function test_admin_corrections_archives_restores_and_membership_changes_reject_stale_uploads(): void
    {
        $p = $this->payload();
        $id = $this->upload($p)->assertCreated()->json('data.id');
        $p = $this->revision($p);
        $assessment = QuranAssessment::firstOrFail();
        $assessment->update(['grade' => 999]);
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertConflict();
        $this->assertEquals(999, $assessment->fresh()->grade);
        $p = $this->revision($p);
        $this->assignment->delete();
        $this->assignment->restore();
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertConflict();
        $p = $this->revision($p);
        $this->group->students()->detach($this->absent);
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertConflict();
    }

    public function test_edits_keep_historical_roster_after_group_membership_changes(): void
    {
        $p = $this->payload();
        $id = $this->upload($p)->assertCreated()->json('data.id');
        $this->group->students()->detach($this->absent);
        $p = $this->revision($p);
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertOk();
        $this->assertDatabaseCount('student_sessions', 2);
    }

    public function test_passage_of_assessed_assignment_cannot_be_changed_and_attempts_do_not_set_passed(): void
    {
        $p = $this->payload();
        $id = $this->upload($p)->assertCreated()->json('data.id');
        $this->assertFalse($this->assignment->fresh()->is_passed);
        $p = $this->revision($p);
        $p['students'][0]['assignments'][] = ['id' => $this->assignment->id, 'surah_name' => 'An-Nas', 'from_ayah' => 2, 'to_ayah' => 6, 'type' => 'revision', 'is_passed' => true];
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertUnprocessable();
        $this->assertFalse($this->assignment->fresh()->is_passed);
        $p['students'][0]['assignments'][0]['from_ayah'] = 1;
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertOk();
        $this->assertTrue($this->assignment->fresh()->is_passed);
    }

    public function test_assessed_assignments_cannot_be_removed_and_archived_attempts_are_not_resurrected(): void
    {
        $p = $this->payload();
        $id = $this->upload($p)->assertCreated()->json('data.id');
        $p = $this->revision($p);
        $p['students'][0]['removed_assignment_ids'] = [$this->assignment->id];
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertUnprocessable();
        unset($p['students'][0]['removed_assignment_ids']);
        QuranAssessment::firstOrFail()->delete();
        $p = $this->revision($p);
        $this->putJson('/api/sessions/'.$id, $p, $this->headers)->assertUnprocessable();
        $this->assertSame(0, QuranAssessment::count());
        $this->assertSame(1, QuranAssessment::withTrashed()->count());
    }

    public function test_server_owns_teacher_id_and_roster_is_validated(): void
    {
        $p = $this->payload();
        $p['teacher_id'] = $this->other->id;
        $this->upload($p)->assertUnprocessable();
        unset($p['teacher_id']);
        array_pop($p['students']);
        $this->upload($p)->assertUnprocessable();
        $p = $this->payload();
        $p['students'][0]['assessments'][0]['grade'] = 'not a number';
        $this->upload($p)->assertUnprocessable();
        $p = $this->payload();
        $p['students'][0]['assignments'][] = ['client_uuid' => (string) Str::uuid(), 'surah_name' => 'An-Nas', 'from_ayah' => 8, 'to_ayah' => 7, 'type' => 'revision', 'is_passed' => false];
        $this->upload($p)->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 0);
    }

    public function test_repeated_attempts_and_acknowledged_assignment_dependencies_keep_original_grades(): void
    {
        $first = $this->payload();
        $uuid = (string) Str::uuid();
        $first['students'][0]['assignments'][] = ['client_uuid' => $uuid, 'surah_name' => 'Al-Mulk', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'revision', 'is_passed' => false];
        $this->upload($first)->assertCreated();
        $second = $this->payload();
        $second['students'][0]['assessments'][] = ['client_uuid' => (string) Str::uuid(), 'assignment_client_uuid' => $uuid, 'grade' => 15];
        $this->upload($second)->assertCreated();
        $this->assertSame(2, $this->assignment->assessments()->count());
        $this->assertEquals(-12.125, $this->assignment->assessments()->oldest('id')->firstOrFail()->grade);
        $this->assertSame(1, QuranAssignment::where('client_uuid', $uuid)->count());
        $this->assertDatabaseCount('quran_assessments', 3);
    }

    public function test_no_base_revision_or_forged_attempt_id_can_overwrite_another_session(): void
    {
        $first = $this->payload();
        $id = $this->upload($first)->assertCreated()->json('data.id');
        $second = $this->payload();
        $foreignId = $this->upload($second)->assertCreated()->json('data.id');
        $update = $this->revision($first);
        unset($update['base_revision']);
        $this->putJson('/api/sessions/'.$id, $update, $this->headers)->assertUnprocessable();
        $update = $this->revision($first);
        $foreign = QuranAssessment::whereHas('studentSession', fn ($q) => $q->where('session_id', $foreignId))->firstOrFail();
        $update['students'][0]['assessments'][0]['id'] = $foreign->id;
        $this->putJson('/api/sessions/'.$id, $update, $this->headers)->assertUnprocessable();
        $this->getJson('/api/sessions/not-an-id', $this->headers)->assertNotFound();
        $this->assertDatabaseCount('quran_assessments', 2);
    }

    public function test_api_support_migration_is_additive_and_reversible_without_losing_school_records(): void
    {
        $migration = require database_path('migrations/2026_11_14_300001_add_teacher_api_support.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('teacher_submissions'));
        $this->assertDatabaseHas('students', ['id' => $this->student->id, 'name' => 'Yusuf']);
        $this->assertDatabaseHas('group_student', ['group_id' => $this->group->id, 'student_id' => $this->student->id]);
        $migration->up();
        $this->assertSame(0, SchoolSync::revision());
        $this->getJson('/api/groups', $this->headers)->assertOk();
    }

    public function test_retries_cannot_expose_a_session_after_administrator_reassignment_or_archive(): void
    {
        $payload = $this->payload();
        $id = $this->upload($payload)->assertCreated()->json('data.id');
        $session = TeachingSession::findOrFail($id);
        $session->update(['teacher_id' => $this->other->id]);
        $this->upload($payload)->assertConflict();
        $this->getJson('/api/sessions/'.$id, $this->headers)->assertNotFound();
        $session->update(['teacher_id' => $this->teacher->id]);
        $session->delete();
        $this->upload($payload)->assertConflict();
        $this->assertSoftDeleted($session);
        $this->assertDatabaseCount('teacher_submissions', 1);
    }

    public function test_surah_catalog_verse_counts_do_not_limit_assignment_ranges(): void
    {
        $payload = $this->payload();
        $payload['students'][0]['assignments'][] = ['client_uuid' => (string) Str::uuid(), 'surah_name' => 'الناس', 'from_ayah' => 1, 'to_ayah' => 100, 'type' => 'revision', 'is_passed' => false];
        $this->upload($payload)->assertCreated();
        $this->assertDatabaseHas('quran_assignments', ['surah_name' => 'الناس', 'to_ayah' => 100]);
    }

    public function test_teacher_cannot_add_another_assessment_for_same_assignment_in_same_session(): void
    {
        $payload = $this->payload();
        $payload['students'][0]['assessments'][] = ['client_uuid' => (string) Str::uuid(), 'quran_assignment_id' => $this->assignment->id, 'grade' => 9];
        $this->upload($payload)->assertUnprocessable();
        $this->assertDatabaseCount('quran_assessments', 0);
        array_pop($payload['students'][0]['assessments']);
        $id = $this->upload($payload)->assertCreated()->json('data.id');
        $update = $this->revision($payload);
        $update['students'][0]['assessments'][] = ['client_uuid' => (string) Str::uuid(), 'quran_assignment_id' => $this->assignment->id, 'grade' => 10];
        $this->putJson('/api/sessions/'.$id, $update, $this->headers)->assertUnprocessable();
        $this->assertDatabaseCount('quran_assessments', 1);
    }

    public function test_repeat_choice_updates_one_global_assignment_status_across_sessions(): void
    {
        $this->assignment->update(['is_passed' => true]);
        $first = $this->payload();
        $first['students'][0]['assignments'][] = ['id' => $this->assignment->id, 'surah_name' => 'الناس', 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'revision', 'is_passed' => true];
        $first['students'][0]['assessments'][0]['needs_repeat'] = true;
        $id = $this->upload($first)->assertCreated()->assertJsonPath('assignment_states.0.is_passed', false)->json('data.id');
        $this->assertFalse($this->assignment->fresh()->is_passed);
        $second = $this->payload();
        $second['students'][0]['assessments'][0]['needs_repeat'] = false;
        $this->upload($second)->assertCreated();
        $this->assertTrue($this->assignment->fresh()->is_passed);
        $this->getJson('/api/sessions/'.$id, $this->headers)->assertOk()->assertJsonPath('data.students.0.assessments.0.is_passed', true);
        $third = $this->payload();
        $third['students'][0]['assessments'][0]['needs_repeat'] = true;
        $this->upload($third)->assertCreated();
        $this->assertFalse($this->assignment->fresh()->is_passed);
        $this->getJson('/api/sessions/'.$id, $this->headers)->assertJsonPath('data.students.0.assessments.0.is_passed', false);
        $this->assertSame(3, $this->assignment->assessments()->count());
    }
}
