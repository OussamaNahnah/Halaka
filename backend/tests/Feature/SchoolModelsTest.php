<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_student_assignment_keeps_multiple_assessment_attempts(): void
    {
        $account = User::factory()->create();
        $teacher = $account->teacher()->create();
        $student = Student::create(['name' => 'Yusuf']);
        $assignment = $student->assignments()->create([
            'surah_name' => 'Al-Mulk',
            'from_ayah' => 1,
            'to_ayah' => 10,
            'type' => 'new_memorization',
        ]);

        $this->assertFalse($assignment->fresh()->is_passed);
        $this->assertTrue($assignment->student->is($student));
        $this->assertTrue($teacher->user->is($account));

        $attempts = collect([-3, 7.512345, 50]);
        foreach ($attempts as $index => $grade) {
            $session = $teacher->sessions()->create(['timestamp' => now()->addDays($index)]);
            $roster = $session->studentSessions()->create(['student_id' => $student->id]);
            $assessment = $roster->assessments()->create([
                'quran_assignment_id' => $assignment->id,
                'grade' => $grade,
                'comment' => $index === 0 ? 'Try again' : null,
            ]);

            $this->assertTrue($assessment->assignment->is($assignment));
            $this->assertTrue($assessment->studentSession->session->is($session));
            $this->assertTrue($roster->student->is($student));
            $this->assertTrue($session->teacher->is($teacher));
            $this->assertNull($session->group);
        }

        $this->assertSame(1, $student->assignments()->count());
        $this->assertSame(3, $student->studentSessions()->count());
        $this->assertEquals($attempts->all(), $assignment->assessments()->orderBy('id')->pluck('grade')->all());
        $this->assertFalse($assignment->fresh()->is_passed);

        $assignment->update(['is_passed' => true]);
        $this->assertTrue($assignment->fresh()->is_passed);
        $this->assertSame(3, $assignment->assessments()->count());
        $this->assertSame('Try again', $assignment->assessments()->oldest('id')->first()->comment);
    }

    public function test_both_pivots_work_and_membership_changes_preserve_the_roster(): void
    {
        $account = User::factory()->create();
        $teacher = Teacher::create(['user_id' => $account->id]);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $adminRole = Role::create(['name' => 'Administrator']);
        $account->roles()->attach([$teacherRole->id, $adminRole->id]);

        $this->assertCount(2, $account->roles);
        $this->assertTrue($teacherRole->users->sole()->is($account));

        $studentAccount = User::factory()->create();
        $student = $studentAccount->student()->create(['name' => 'Yusuf']);
        $sibling = Student::create(['name' => 'Maryam']);
        $first = Group::create(['name' => 'Morning']);
        $second = Group::create(['name' => 'Evening']);
        $student->groups()->attach([$first->id, $second->id]);
        $first->students()->attach($sibling->id);

        $this->assertCount(2, $student->groups);
        $this->assertCount(2, $first->students);
        $this->assertTrue($student->user->is($studentAccount));

        $session = $first->sessions()->create(['teacher_id' => $teacher->id, 'timestamp' => now()]);
        $roster = $session->studentSessions()->create(['student_id' => $student->id]);
        $student->groups()->detach($first->id);

        $this->assertTrue($session->group->is($first));
        $this->assertTrue($roster->fresh()->student->is($student));
        $this->assertSame('not_marked', $roster->fresh()->attendance_status);
        $this->assertSame('in_progress', $session->fresh()->status);
        $this->assertNotNull($second->students->sole()->pivot->created_at);
    }

    public function test_duplicate_group_memberships_are_rejected(): void
    {
        $student = Student::create(['name' => 'Yusuf']);
        $group = Group::create(['name' => 'Morning']);
        $group->students()->attach($student->id);

        $this->expectException(QueryException::class);
        $student->groups()->attach($group->id);
    }
}
