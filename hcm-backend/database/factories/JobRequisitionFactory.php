<?php

namespace Database\Factories;

use App\Models\JobRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRequisition>
 */
class JobRequisitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'       => \App\Models\Tenant::factory(),
            'department_id'   => null,
            'position_id'     => null,
            'hiring_manager_id' => null,
            'approved_by'     => null,
            'title'           => fake()->jobTitle(),
            'description'     => fake()->paragraph(),
            'requirements'    => fake()->paragraph(),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
            'work_location'   => fake()->city(),
            'is_remote'       => fake()->boolean(30),
            'headcount'       => fake()->numberBetween(1, 5),
            'salary_min'      => 50000,
            'salary_max'      => 100000,
            'currency'        => 'USD',
            'status'          => 'open',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);
    }
}
