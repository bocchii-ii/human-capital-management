<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'department'  => new DepartmentResource($this->whenLoaded('department')),
            'position'    => new PositionResource($this->whenLoaded('position')),
            'tasks'       => OnboardingTaskResource::collection($this->whenLoaded('tasks')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
