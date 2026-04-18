<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseModule>
 */
class CourseModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'course_id'   => Course::factory(),
            'title'       => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sort_order'  => 0,
        ];
    }
}
