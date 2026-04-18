<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tenant_id'           => $this->tenant_id,
            'employee_id'         => $this->employee_id,
            'course_id'           => $this->course_id,
            'learning_path_id'    => $this->learning_path_id,
            'enrolled_by'         => $this->enrolled_by,
            'status'              => $this->status,
            'progress_percentage' => $this->progress_percentage,
            'enrolled_at'         => $this->enrolled_at,
            'started_at'          => $this->started_at,
            'completed_at'        => $this->completed_at,
            'due_date'            => $this->due_date,
            'employee'            => new EmployeeResource($this->whenLoaded('employee')),
            'course'              => new CourseResource($this->whenLoaded('course')),
            'lesson_progress'     => LessonProgressResource::collection($this->whenLoaded('lessonProgress')),
            'certificate'         => new CertificateResource($this->whenLoaded('certificate')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
