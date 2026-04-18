<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningPathCourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learning_path_id' => LearningPath::factory(),
            'course_id'        => Course::factory(),
            'sort_order'       => $this->faker->numberBetween(0, 10),
            'is_required'      => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }
}
