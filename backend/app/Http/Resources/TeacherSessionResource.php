<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'client_uuid' => $this->client_uuid, 'revision' => (int) $this->revision, 'teacher_id' => $this->teacher_id, 'group' => $this->group ? ['id' => $this->group->id, 'name' => $this->group->name, 'archived' => $this->group->trashed()] : null, 'timestamp' => $this->timestamp->toIso8601String(), 'status' => $this->status, 'students' => $this->whenLoaded('studentSessions', fn () => $this->studentSessions->map(fn ($row) => ['id' => $row->id, 'student_id' => $row->student_id, 'name' => $row->student->name, 'archived' => $row->student->trashed(), 'attendance_status' => $row->attendance_status, 'evaluated_at' => $row->evaluated_at?->toIso8601String(), 'assessments' => $row->assessments->map(fn ($assessment) => ['id' => $assessment->id, 'client_uuid' => $assessment->client_uuid, 'quran_assignment_id' => $assessment->quran_assignment_id, 'assignment_archived' => $assessment->assignment->trashed(), 'is_passed' => $assessment->assignment->is_passed, 'grade' => $assessment->grade, 'comment' => $assessment->comment])]))];
    }
}
