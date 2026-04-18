<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'question_id' => Question::factory(),
            'option_text' => $this->faker->sentence(3),
            'is_correct'  => false,
            'sort_order'  => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(['is_correct' => true]);
    }
}
