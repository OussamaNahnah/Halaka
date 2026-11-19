<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'teacher_id' => $this->teacher->id, 'name' => $this->name, 'email' => $this->email];
    }
}
