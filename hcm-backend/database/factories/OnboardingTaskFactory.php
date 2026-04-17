<?php

namespace Database\Factories;

use App\Models\OnboardingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTask>
 */
class OnboardingTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'              => \App\Models\Tenant::factory(),
            'onboarding_template_id' => \App\Models\OnboardingTemplate::factory(),
            'title'                  => fake()->sentence(5),
            'description'            => fake()->paragraph(),
            'assignee_role'          => fake()->randomElement(['new_hire', 'hr', 'manager', 'it']),
            'due_days_offset'        => fake()->numberBetween(0, 30),
            'is_required'            => true,
            'sort_order'             => fake()->numberBetween(0, 100),
        ];
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }
}
