<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'scheduled_at'     => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'location'         => $this->location,
            'notes'            => $this->notes,
            'result'           => $this->result,
            'feedback'         => $this->feedback,
            'interviewer'      => $this->when($this->relationLoaded('interviewer'), fn () => [
                'id'   => $this->interviewer?->id,
                'name' => $this->interviewer?->name,
            ]),
            'application'      => new ApplicationResource($this->whenLoaded('application')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
