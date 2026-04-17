<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'      => \App\Models\Tenant::factory(),
            'application_id' => \App\Models\Application::factory(),
            'salary'         => fake()->numberBetween(50000, 150000),
            'currency'       => 'USD',
            'start_date'     => now()->addDays(30)->toDateString(),
            'expires_at'     => now()->addDays(7)->toDateString(),
            'status'         => 'draft',
            'letter_path'    => null,
            'sent_at'        => null,
            'signed_at'      => null,
            'notes'          => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => 'accepted', 'sent_at' => now(), 'signed_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => 'declined', 'sent_at' => now()]);
    }
}
