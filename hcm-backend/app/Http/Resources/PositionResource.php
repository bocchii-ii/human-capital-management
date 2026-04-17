<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'level'       => $this->level,
            'is_active'   => $this->is_active,
            'department'  => new DepartmentResource($this->whenLoaded('department')),
            'employees_count' => $this->when(isset($this->employees_count), $this->employees_count),
            'created_at'  => $this->created_at,
        ];
    }
}
