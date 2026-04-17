<?php

namespace Database\Factories;

use App\Models\OnboardingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingAssignment>
 */
class OnboardingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'              => \App\Models\Tenant::factory(),
            'employee_id'            => \App\Models\Employee::factory(),
            'onboarding_template_id' => \App\Models\OnboardingTemplate::factory(),
            'assigned_by'            => \App\Models\User::factory(),
            'start_date'             => fake()->dateTimeBetween('-1 month', '+1 month'),
            'status'                 => 'pending',
            'completed_at'           => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }
}
