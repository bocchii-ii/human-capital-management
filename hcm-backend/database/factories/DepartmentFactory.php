<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'   => \App\Models\Tenant::factory(),
            'parent_id'   => null,
            'name'        => fake()->unique()->words(2, true),
            'code'        => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->sentence(),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
