<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'onboarding_template_id' => $this->onboarding_template_id,
            'title'                  => $this->title,
            'description'            => $this->description,
            'assignee_role'          => $this->assignee_role,
            'due_days_offset'        => $this->due_days_offset,
            'is_required'            => $this->is_required,
            'sort_order'             => $this->sort_order,
            'created_at'             => $this->created_at,
        ];
    }
}
