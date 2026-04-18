<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'quiz_id'          => $this->quiz_id,
            'employee_id'      => $this->employee_id,
            'attempt_number'   => $this->attempt_number,
            'status'           => $this->status,
            'score_percentage' => $this->score_percentage,
            'passed'           => $this->passed,
            'started_at'       => $this->started_at,
            'submitted_at'     => $this->submitted_at,
            'quiz'             => new QuizResource($this->whenLoaded('quiz')),
            'answers'          => QuizAttemptAnswerResource::collection($this->whenLoaded('answers')),
            'created_at'       => $this->created_at,
        ];
    }
}
