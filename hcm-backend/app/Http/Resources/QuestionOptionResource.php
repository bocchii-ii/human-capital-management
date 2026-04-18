<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'question_id' => $this->question_id,
            'option_text' => $this->option_text,
            'sort_order'  => $this->sort_order,
            'is_correct'  => $this->when(
                $request->user()?->can('training.course.manage'),
                fn () => (bool) $this->is_correct
            ),
            'created_at'  => $this->created_at,
        ];
    }
}
