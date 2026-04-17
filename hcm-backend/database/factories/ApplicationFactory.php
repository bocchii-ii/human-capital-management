<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'          => \App\Models\Tenant::factory(),
            'job_requisition_id' => \App\Models\JobRequisition::factory(),
            'applicant_id'       => \App\Models\Applicant::factory(),
            'stage'              => 'applied',
            'cover_letter'       => fake()->paragraph(),
            'notes'              => null,
            'rejection_reason'   => null,
            'stage_changed_at'   => now(),
        ];
    }

    public function inStage(string $stage): static
    {
        return $this->state(['stage' => $stage]);
    }

    public function rejected(): static
    {
        return $this->state([
            'stage'            => 'rejected',
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function hired(): static
    {
        return $this->state(['stage' => 'hired']);
    }
}
