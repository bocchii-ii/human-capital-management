<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'quiz_id'       => $this->quiz_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'points'        => $this->points,
            'sort_order'    => $this->sort_order,
            'explanation'   => $this->explanation,
            'options'       => QuestionOptionResource::collection($this->whenLoaded('options')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
