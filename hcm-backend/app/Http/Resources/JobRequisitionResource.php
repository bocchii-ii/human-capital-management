<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobRequisitionResource extends JsonResource
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
            'title'            => $this->title,
            'description'      => $this->description,
            'requirements'     => $this->requirements,
            'employment_type'  => $this->employment_type,
            'work_location'    => $this->work_location,
            'is_remote'        => $this->is_remote,
            'headcount'        => $this->headcount,
            'salary_min'       => $this->salary_min,
            'salary_max'       => $this->salary_max,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'approved_at'      => $this->approved_at,
            'closed_at'        => $this->closed_at,
            'department'       => new DepartmentResource($this->whenLoaded('department')),
            'position'         => new PositionResource($this->whenLoaded('position')),
            'hiring_manager'   => $this->when($this->relationLoaded('hiringManager'), fn () => [
                'id'   => $this->hiringManager?->id,
                'name' => $this->hiringManager?->name,
            ]),
            'applications_count' => $this->when(isset($this->applications_count), $this->applications_count),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
