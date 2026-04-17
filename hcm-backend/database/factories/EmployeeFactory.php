<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'user_id'         => null,
            'department_id'   => null,
            'position_id'     => null,
            'manager_id'      => null,
            'employee_number' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'first_name'      => fake()->firstName(),
            'last_name'       => fake()->lastName(),
            'email'           => fake()->unique()->safeEmail(),
            'phone'           => fake()->phoneNumber(),
            'hire_date'       => fake()->dateTimeBetween('-5 years', 'now'),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
            'status'          => 'active',
            'work_location'   => fake()->city(),
        ];
    }

    public function terminated(): static
    {
        return $this->state([
            'status'           => 'terminated',
            'termination_date' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
