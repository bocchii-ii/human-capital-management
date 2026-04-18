<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'    => Tenant::factory(),
            'created_by'   => null,
            'title'        => fake()->sentence(4),
            'slug'         => null,
            'description'  => fake()->paragraph(),
            'category'     => fake()->randomElement(['compliance', 'technical', 'soft_skills']),
            'status'       => 'draft',
            'is_active'    => true,
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft', 'published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()]);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived', 'published_at' => now()->subMonth()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
