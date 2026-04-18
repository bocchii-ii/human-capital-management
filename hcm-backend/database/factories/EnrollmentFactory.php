<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id'           => $tenant->id,
            'employee_id'         => Employee::factory()->create(['tenant_id' => $tenant->id])->id,
            'course_id'           => Course::factory()->create(['tenant_id' => $tenant->id])->id,
            'learning_path_id'    => null,
            'enrolled_by'         => null,
            'status'              => 'enrolled',
            'progress_percentage' => 0.00,
            'enrolled_at'         => now(),
            'started_at'          => null,
            'completed_at'        => null,
            'due_date'            => null,
        ];
    }

    public function enrolled(): static
    {
        return $this->state([
            'status'      => 'enrolled',
            'started_at'  => null,
            'completed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status'              => 'in_progress',
            'started_at'          => now()->subDays(2),
            'progress_percentage' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status'              => 'completed',
            'started_at'          => now()->subDays(10),
            'completed_at'        => now()->subDay(),
            'progress_percentage' => 100.00,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state([
            'status' => 'withdrawn',
        ]);
    }
}
