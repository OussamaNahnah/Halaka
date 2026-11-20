<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'client_uuid' => $this->client_uuid, 'student_id' => $this->student_id, 'created_in_session_id' => $this->created_in_session_id, 'surah_name' => $this->surah_name, 'from_ayah' => $this->from_ayah, 'to_ayah' => $this->to_ayah, 'type' => $this->type, 'is_passed' => $this->is_passed, 'assessments' => $this->whenLoaded('assessments', fn () => $this->assessments->map(fn ($row) => ['id' => $row->id, 'client_uuid' => $row->client_uuid, 'grade' => $row->grade, 'comment' => $row->comment, 'session_id' => $row->studentSession->session_id, 'timestamp' => $row->studentSession->session->timestamp->toIso8601String()]))];
    }
}
