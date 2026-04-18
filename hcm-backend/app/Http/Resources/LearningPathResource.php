<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tenant_id'            => $this->tenant_id,
            'title'                => $this->title,
            'description'          => $this->description,
            'target_role'          => $this->target_role,
            'target_department_id' => $this->target_department_id,
            'is_active'            => $this->is_active,
            'path_courses'         => LearningPathCourseResource::collection($this->whenLoaded('pathCourses')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
