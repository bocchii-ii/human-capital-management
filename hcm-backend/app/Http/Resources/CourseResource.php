<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'category'     => $this->category,
            'status'       => $this->status,
            'is_active'    => $this->is_active,
            'published_at' => $this->published_at,
            'creator'      => new UserResource($this->whenLoaded('creator')),
            'modules'      => CourseModuleResource::collection($this->whenLoaded('modules')),
            'created_at'   => $this->created_at,
        ];
    }
}
