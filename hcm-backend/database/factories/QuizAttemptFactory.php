<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Quiz;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'quiz_id'          => Quiz::factory(),
            'employee_id'      => Employee::factory(),
            'attempt_number'   => 1,
            'status'           => 'in_progress',
            'score_percentage' => null,
            'passed'           => null,
            'started_at'       => now(),
            'submitted_at'     => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state([
            'status'           => 'in_progress',
            'score_percentage' => null,
            'passed'           => null,
            'submitted_at'     => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state([
            'status'           => 'submitted',
            'score_percentage' => 80.00,
            'passed'           => true,
            'submitted_at'     => now(),
        ]);
    }

    public function passed(): static
    {
        return $this->state([
            'status'           => 'submitted',
            'score_percentage' => 90.00,
            'passed'           => true,
            'submitted_at'     => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'           => 'submitted',
            'score_percentage' => 40.00,
            'passed'           => false,
            'submitted_at'     => now(),
        ]);
    }
}
