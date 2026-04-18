<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'lesson_id'          => $this->lesson_id,
            'title'              => $this->title,
            'description'        => $this->description,
            'pass_threshold'     => $this->pass_threshold,
            'max_attempts'       => $this->max_attempts,
            'time_limit_minutes' => $this->time_limit_minutes,
            'shuffle_questions'  => $this->shuffle_questions,
            'questions'          => QuestionResource::collection($this->whenLoaded('questions')),
            'created_at'         => $this->created_at,
        ];
    }
}
