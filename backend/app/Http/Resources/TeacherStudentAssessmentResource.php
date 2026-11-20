<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherStudentAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade' => $this->grade,
            'comment' => $this->comment,
            'session_id' => $this->studentSession->session_id,
            'timestamp' => $this->studentSession->session->timestamp->toIso8601String(),
            'assignment' => [
                'id' => $this->assignment->id,
                'surah_name' => $this->assignment->surah_name,
                'from_ayah' => $this->assignment->from_ayah,
                'to_ayah' => $this->assignment->to_ayah,
                'type' => $this->assignment->type,
                'is_passed' => $this->assignment->is_passed,
                'archived' => $this->assignment->trashed(),
            ],
        ];
    }
}
