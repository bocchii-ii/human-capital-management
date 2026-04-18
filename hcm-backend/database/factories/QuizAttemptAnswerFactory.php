<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_attempt_id'     => QuizAttempt::factory(),
            'question_id'         => Question::factory(),
            'selected_option_ids' => [],
            'is_correct'          => false,
        ];
    }
}
