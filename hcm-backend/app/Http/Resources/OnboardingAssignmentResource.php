<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status,
            'start_date'  => $this->start_date?->toDateString(),
            'completed_at' => $this->completed_at,
            'employee'    => new EmployeeResource($this->whenLoaded('employee')),
            'template'    => new OnboardingTemplateResource($this->whenLoaded('template')),
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'task_completions' => OnboardingTaskCompletionResource::collection($this->whenLoaded('taskCompletions')),
            'created_at'  => $this->created_at,
        ];
    }
}
