<?php

namespace Tests\Feature;

use App\Models\QuranAssessment;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStudentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;

    private Teacher $other;

    private Student $student;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Teacher']);
        $user = User::factory()->create(['password' => 'password123']);
        $user->roles()->attach($role);
        $this->teacher = Teacher::create(['user_id' => $user->id]);
        $other = User::factory()->create();
        $other->roles()->attach($role);
        $this->other = Teacher::create(['user_id' => $other->id]);
        $this->student = Student::create(['name' => 'Student', 'parent_phone' => 'private-contact']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk()->json('access_token');
        $this->headers = ['Authorization' => 'Bearer '.$token];
    }

    private function attempt(Teacher $teacher, string $attendance, ?float $grade, string $status = 'completed'): ?QuranAssessment
    {
        $session = $teacher->sessions()->create(['timestamp' => now(), 'status' => $status]);
        $roster = $session->studentSessions()->create(['student_id' => $this->student->id, 'attendance_status' => $attendance]);
        $assignment = $this->student->assignments()->create(['surah_name' => 'الفاتحة', 'from_ayah' => 1, 'to_ayah' => 7, 'type' => 'revision']);

        if ($grade === null) {
            return null;
        }

        return $roster->assessments()->create(['quran_assignment_id' => $assignment->id, 'grade' => $grade, 'comment' => 'Read carefully']);
    }

    public function test_summary_counts_owned_completed_records_and_zero_grades(): void
    {
        $this->attempt($this->teacher, 'present', 0);
        $this->attempt($this->teacher, 'present', 10);
        $this->attempt($this->teacher, 'absent', null);
        $this->attempt($this->other, 'present', 100);
        $this->attempt($this->teacher, 'present', 50, 'in_progress');
        $this->attempt($this->teacher, 'present', 99)->studentSession->session->delete();
        $this->attempt($this->teacher, 'present', 99)->studentSession->delete();
        $deleted = $this->attempt($this->teacher, 'present', 99);
        $deleted->delete();
        $data = $this->getJson('/api/students/'.$this->student->id.'/summary', $this->headers)->assertOk()->assertDontSee('private-contact')->json('data');
        $this->assertSame(3, $data['present_count']);
        $this->assertSame(1, $data['absent_count']);
        $this->assertEquals(5, $data['average_grade']);
        $this->assertSame(2, $data['assessment_count']);
    }

    public function test_histories_paginate_and_exclude_other_teachers(): void
    {
        $own = $this->attempt($this->teacher, 'present', -1.125);
        $this->attempt($this->teacher, 'present', 0);
        $this->attempt($this->other, 'present', 123);
        $own->assignment->delete();
        $url = '/api/students/'.$this->student->id;
        $this->getJson($url.'/assessments?per_page=1', $this->headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson($url.'/assessments?per_page=1&page=2', $this->headers)->assertOk()->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.assignment.archived', true)->assertJsonPath('data.0.comment', 'Read carefully');
        $this->getJson($url.'/assignments?per_page=1&status=pending', $this->headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.assessments');
        $this->getJson($url.'/assignments', $this->headers)->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data' => [['assessments']]]);
        $this->getJson($url.'/assessments?per_page=101', $this->headers)->assertUnprocessable();
        $this->getJson($url.'/assignments?status=invalid', $this->headers)->assertUnprocessable();
    }

    public function test_search_pagination_empty_statistics_and_access(): void
    {
        Student::create(['name' => 'Another student']);
        $url = '/api/students/'.$this->student->id;
        $this->getJson('/api/students')->assertUnauthorized();
        $this->getJson('/api/students?per_page=1', $this->headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertDontSee('private-contact');
        $this->getJson('/api/students?search=Student', $this->headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url.'/summary', $this->headers)->assertOk()->assertJsonPath('data.average_grade', null)->assertJsonPath('data.present_count', 0);
        $this->student->delete();
        $this->getJson($url.'/summary', $this->headers)->assertNotFound();
        $this->getJson($url.'/assessments', $this->headers)->assertNotFound();
    }
}
