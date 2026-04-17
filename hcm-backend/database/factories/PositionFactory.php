<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'     => \App\Models\Tenant::factory(),
            'department_id' => null,
            'title'         => fake()->jobTitle(),
            'description'   => fake()->sentence(),
            'level'         => fake()->randomElement(['junior', 'mid', 'senior', 'lead']),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
