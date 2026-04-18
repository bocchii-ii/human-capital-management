<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'enrollment_id' => Enrollment::factory(),
            'lesson_id'     => Lesson::factory(),
            'status'        => 'not_started',
            'completed_at'  => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }
}
