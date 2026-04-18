<?php

namespace Tests\Unit\Models;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class CertificateTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeCertificate(array $overrides = []): Certificate
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id'           => $this->tenant->id,
            'employee_id'         => $employee->id,
            'course_id'           => $course->id,
            'status'              => 'completed',
            'progress_percentage' => 100.00,
            'enrolled_at'         => now()->subDays(10),
            'completed_at'        => now(),
        ]);

        return Certificate::create(array_merge([
            'tenant_id'          => $this->tenant->id,
            'enrollment_id'      => $enrollment->id,
            'employee_id'        => $employee->id,
            'course_id'          => $course->id,
            'certificate_number' => 'CERT-20260419-' . strtoupper(Str::random(8)),
            'issued_at'          => now(),
        ], $overrides));
    }

    public function test_fillable_fields(): void
    {
        $cert = new Certificate();
        $this->assertEqualsCanonicalizing([
            'tenant_id', 'enrollment_id', 'employee_id', 'course_id',
            'certificate_number', 'issued_at', 'pdf_path',
        ], $cert->getFillable());
    }

    public function test_issued_at_is_cast_to_datetime(): void
    {
        $cert = $this->makeCertificate();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cert->issued_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $cert = $this->makeCertificate();
        $this->assertInstanceOf(Tenant::class, $cert->tenant);
        $this->assertEquals($this->tenant->id, $cert->tenant->id);
    }

    public function test_belongs_to_enrollment(): void
    {
        $cert = $this->makeCertificate();
        $this->assertInstanceOf(Enrollment::class, $cert->enrollment);
    }

    public function test_belongs_to_employee(): void
    {
        $cert = $this->makeCertificate();
        $this->assertInstanceOf(Employee::class, $cert->employee);
    }

    public function test_belongs_to_course(): void
    {
        $cert = $this->makeCertificate();
        $this->assertInstanceOf(Course::class, $cert->course);
    }

    public function test_certificate_number_is_unique(): void
    {
        $cert1 = $this->makeCertificate();

        $employee2   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course2     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment2 = Enrollment::create([
            'tenant_id'    => $this->tenant->id,
            'employee_id'  => $employee2->id,
            'course_id'    => $course2->id,
            'status'       => 'completed',
            'enrolled_at'  => now(),
            'completed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Certificate::create([
            'tenant_id'          => $this->tenant->id,
            'enrollment_id'      => $enrollment2->id,
            'employee_id'        => $employee2->id,
            'course_id'          => $course2->id,
            'certificate_number' => $cert1->certificate_number,
            'issued_at'          => now(),
        ]);
    }

    public function test_pdf_path_is_nullable(): void
    {
        $cert = $this->makeCertificate();
        $this->assertNull($cert->pdf_path);
    }

    public function test_enrollment_has_one_certificate(): void
    {
        $cert       = $this->makeCertificate();
        $enrollment = $cert->enrollment;

        $this->assertInstanceOf(Certificate::class, $enrollment->certificate);
        $this->assertEquals($cert->id, $enrollment->certificate->id);
    }
}
