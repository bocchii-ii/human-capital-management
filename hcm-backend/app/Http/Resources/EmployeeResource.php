<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'employee_number'  => $this->employee_number,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'full_name'        => $this->full_name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'avatar_path'      => $this->avatar_path,
            'hire_date'        => $this->hire_date?->toDateString(),
            'termination_date' => $this->termination_date?->toDateString(),
            'employment_type'  => $this->employment_type,
            'status'           => $this->status,
            'work_location'    => $this->work_location,
            'department'       => new DepartmentResource($this->whenLoaded('department')),
            'position'         => new PositionResource($this->whenLoaded('position')),
            'manager'          => new self($this->whenLoaded('manager')),
            'direct_reports'   => self::collection($this->whenLoaded('directReports')),
            'created_at'       => $this->created_at,
        ];
    }
}
