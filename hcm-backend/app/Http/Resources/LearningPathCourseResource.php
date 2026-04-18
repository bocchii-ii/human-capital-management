<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'learning_path_id' => $this->learning_path_id,
            'course_id'        => $this->course_id,
            'sort_order'       => $this->sort_order,
            'is_required'      => $this->is_required,
            'course'           => new CourseResource($this->whenLoaded('course')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
