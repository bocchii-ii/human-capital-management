<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppNotificationFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'user_id'   => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'type'      => $this->faker->randomElement(['enrollment.completed', 'certificate.issued', 'onboarding.assigned']),
            'title'     => $this->faker->sentence(5),
            'body'      => $this->faker->sentence(12),
            'data'      => null,
            'read_at'   => null,
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()->subHour()]);
    }
}
