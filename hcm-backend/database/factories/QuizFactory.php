<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'          => Tenant::factory(),
            'lesson_id'          => Lesson::factory(),
            'title'              => $this->faker->sentence(4),
            'description'        => $this->faker->paragraph(),
            'pass_threshold'     => 70,
            'max_attempts'       => null,
            'time_limit_minutes' => null,
            'shuffle_questions'  => false,
        ];
    }
}
