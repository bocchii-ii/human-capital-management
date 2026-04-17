<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTaskCompletionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'task'         => new OnboardingTaskResource($this->whenLoaded('task')),
            'completed_by' => new UserResource($this->whenLoaded('completedBy')),
            'completed_at' => $this->completed_at,
            'notes'        => $this->notes,
        ];
    }
}
