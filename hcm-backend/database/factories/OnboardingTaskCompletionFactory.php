<?php

namespace Database\Factories;

use App\Models\OnboardingTaskCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTaskCompletion>
 */
class OnboardingTaskCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'onboarding_assignment_id' => \App\Models\OnboardingAssignment::factory(),
            'onboarding_task_id'       => \App\Models\OnboardingTask::factory(),
            'completed_by'             => \App\Models\User::factory(),
            'completed_at'             => now(),
            'notes'                    => fake()->optional()->sentence(),
        ];
    }
}
