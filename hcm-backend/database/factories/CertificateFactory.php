<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateFactory extends Factory
{
    public function definition(): array
    {
        $tenant     = Tenant::factory()->create();
        $employee   = Employee::factory()->create(['tenant_id' => $tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $tenant->id]);
        $enrollment = Enrollment::factory()->completed()->create([
            'tenant_id'   => $tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
        ]);

        return [
            'tenant_id'          => $tenant->id,
            'enrollment_id'      => $enrollment->id,
            'employee_id'        => $employee->id,
            'course_id'          => $course->id,
            'certificate_number' => 'CERT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
            'issued_at'          => now(),
            'pdf_path'           => null,
        ];
    }

    public function withPdf(): static
    {
        return $this->state([
            'pdf_path' => 'certificates/1/CERT-20260419-ABCDEFGH.pdf',
        ]);
    }
}
