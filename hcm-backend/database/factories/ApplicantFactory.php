<?php

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'    => \App\Models\Tenant::factory(),
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'email'        => fake()->unique()->safeEmail(),
            'phone'        => fake()->phoneNumber(),
            'resume_path'  => null,
            'linkedin_url' => 'https://linkedin.com/in/' . fake()->userName(),
            'source'       => fake()->randomElement(['linkedin', 'referral', 'careers_page', 'indeed', 'direct']),
        ];
    }
}
