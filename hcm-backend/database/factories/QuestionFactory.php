<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'quiz_id'       => Quiz::factory(),
            'question_text' => $this->faker->sentence() . '?',
            'question_type' => 'single_choice',
            'points'        => 1,
            'sort_order'    => 0,
            'explanation'   => null,
        ];
    }

    public function singleChoice(): static
    {
        return $this->state(['question_type' => 'single_choice']);
    }

    public function multipleChoice(): static
    {
        return $this->state(['question_type' => 'multiple_choice']);
    }

    public function trueFalse(): static
    {
        return $this->state(['question_type' => 'true_false']);
    }
}
