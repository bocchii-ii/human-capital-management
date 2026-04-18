<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'question_id'         => $this->question_id,
            'selected_option_ids' => $this->selected_option_ids,
            'is_correct'          => $this->is_correct,
        ];
    }
}
