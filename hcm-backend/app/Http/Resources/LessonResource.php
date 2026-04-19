<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'course_module_id' => $this->course_module_id,
            'title'            => $this->title,
            'content_type'     => $this->content_type,
            'content'          => $this->content,
            'video_url'        => $this->video_url,
            'file_url'         => $this->file_url,
            'duration_minutes' => $this->duration_minutes,
            'sort_order'       => $this->sort_order,
            'is_required'      => $this->is_required,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
