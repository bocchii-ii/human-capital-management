<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'name'        => $this->name,
            'code'        => $this->code,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'parent_id'   => $this->parent_id,
            'parent'      => new self($this->whenLoaded('parent')),
            'children'    => self::collection($this->whenLoaded('children')),
            'positions_count' => $this->when(isset($this->positions_count), $this->positions_count),
            'employees_count' => $this->when(isset($this->employees_count), $this->employees_count),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
