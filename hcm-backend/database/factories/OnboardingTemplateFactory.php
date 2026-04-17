<?php

namespace Database\Factories;

use App\Models\OnboardingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTemplate>
 */
class OnboardingTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => \App\Models\Tenant::factory(),
            'department_id' => null,
            'position_id'   => null,
            'title'         => fake()->sentence(4),
            'description'   => fake()->paragraph(),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
