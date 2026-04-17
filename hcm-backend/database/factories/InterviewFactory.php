<?php

namespace Database\Factories;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'        => \App\Models\Tenant::factory(),
            'application_id'   => \App\Models\Application::factory(),
            'interviewer_id'   => null,
            'type'             => fake()->randomElement(['technical', 'hr', 'culture', 'panel']),
            'scheduled_at'     => fake()->dateTimeBetween('now', '+2 weeks'),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'location'         => fake()->randomElement(['Conference Room A', 'https://meet.google.com/abc-xyz']),
            'notes'            => null,
            'result'           => null,
            'feedback'         => null,
        ];
    }

    public function passed(): static
    {
        return $this->state(['result' => 'pass', 'feedback' => fake()->sentence()]);
    }

    public function failed(): static
    {
        return $this->state(['result' => 'fail', 'feedback' => fake()->sentence()]);
    }
}
