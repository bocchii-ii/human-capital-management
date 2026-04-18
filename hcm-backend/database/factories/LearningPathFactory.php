<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningPathFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'title'       => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'target_role' => $this->faker->randomElement(['Engineer', 'Manager', 'Sales', null]),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
