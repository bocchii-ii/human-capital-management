<?php

namespace Tests\Feature\Api\V1;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('local');
    }

    private function makeCompletedEnrollment(): Enrollment
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        return Enrollment::create([
            'tenant_id'           => $this->tenant->id,
            'employee_id'         => $employee->id,
            'course_id'           => $course->id,
            'status'              => 'completed',
            'progress_percentage' => 100.00,
            'enrolled_at'         => now()->subDays(5),
            'completed_at'        => now(),
        ]);
    }

    private function makeCertificate(?Enrollment $enrollment = null): Certificate
    {
        $enrollment ??= $this->makeCompletedEnrollment();

        return Certificate::create([
            'tenant_id'          => $this->tenant->id,
            'enrollment_id'      => $enrollment->id,
            'employee_id'        => $enrollment->employee_id,
            'course_id'          => $enrollment->course_id,
            'certificate_number' => 'CERT-20260419-' . strtoupper(Str::random(8)),
            'issued_at'          => now(),
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_certificates(): void
    {
        $this->makeCertificate();
        $this->makeCertificate();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_employee_sees_own_certificates_only(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'completed',
            'enrolled_at' => now(), 'completed_at' => now(),
        ]);

        Certificate::create([
            'tenant_id' => $this->tenant->id, 'enrollment_id' => $enrollment->id,
            'employee_id' => $employee->id, 'course_id' => $course->id,
            'certificate_number' => 'CERT-OWN-001', 'issued_at' => now(),
        ]);

        // Another employee's certificate
        $this->makeCertificate();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/certificates')->assertUnauthorized();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_certificate(): void
    {
        $cert = $this->makeCertificate();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/certificates/{$cert->id}")
            ->assertOk()
            ->assertJsonPath('data.certificate_number', $cert->certificate_number)
            ->assertJsonPath('data.has_pdf', false);
    }

    public function test_show_employee_can_view_own_certificate(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'completed',
            'enrolled_at' => now(), 'completed_at' => now(),
        ]);

        $cert = Certificate::create([
            'tenant_id' => $this->tenant->id, 'enrollment_id' => $enrollment->id,
            'employee_id' => $employee->id, 'course_id' => $course->id,
            'certificate_number' => 'CERT-EMP-001', 'issued_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/certificates/{$cert->id}")
            ->assertOk();
    }

    public function test_show_employee_cannot_view_other_certificate(): void
    {
        $user = $this->userWithRole('Employee');
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        $cert = $this->makeCertificate();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/certificates/{$cert->id}")
            ->assertForbidden();
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_download_returns_404_when_pdf_missing(): void
    {
        $cert = $this->makeCertificate();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/certificates/{$cert->id}/download")
            ->assertNotFound();
    }

    public function test_download_streams_pdf_when_available(): void
    {
        $cert = $this->makeCertificate();

        $path = "certificates/{$cert->tenant_id}/{$cert->certificate_number}.pdf";
        Storage::put($path, '%PDF-1.4 fake pdf content');
        $cert->update(['pdf_path' => $path]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->get("/api/v1/certificates/{$cert->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ── Issue Certificate ─────────────────────────────────────────────────────

    public function test_issue_certificate_generates_certificate_for_completed_enrollment(): void
    {
        $enrollment = $this->makeCompletedEnrollment();
        $enrollment->load(['employee', 'course']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate")
            ->assertOk()
            ->assertJsonPath('data.enrollment_id', $enrollment->id)
            ->assertJsonPath('data.employee_id', $enrollment->employee_id)
            ->assertJsonPath('data.course_id', $enrollment->course_id)
            ->assertJsonStructure(['data' => ['id', 'certificate_number', 'issued_at', 'has_pdf']]);

        $this->assertDatabaseHas('certificates', [
            'enrollment_id' => $enrollment->id,
            'tenant_id'     => $this->tenant->id,
        ]);
    }

    public function test_issue_certificate_rejects_non_completed_enrollment(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'in_progress',
            'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate")
            ->assertUnprocessable();
    }

    public function test_issue_certificate_reissuance_keeps_same_certificate_number(): void
    {
        $enrollment = $this->makeCompletedEnrollment();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate");

        $firstNumber = Certificate::where('enrollment_id', $enrollment->id)->value('certificate_number');

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate");

        $secondNumber = Certificate::where('enrollment_id', $enrollment->id)->value('certificate_number');

        $this->assertEquals($firstNumber, $secondNumber);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_issue_certificate_forbidden_for_employee_role(): void
    {
        $user       = $this->userWithRole('Employee');
        $enrollment = $this->makeCompletedEnrollment();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate")
            ->assertForbidden();
    }

    public function test_issue_certificate_requires_authentication(): void
    {
        $enrollment = $this->makeCompletedEnrollment();

        $this->postJson("/api/v1/enrollments/{$enrollment->id}/issue-certificate")
            ->assertUnauthorized();
    }

    // ── Auto-generation on completion ─────────────────────────────────────────

    public function test_certificate_auto_generated_when_all_lessons_completed(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user     = $this->userWithRole('Employee');
        $employee->update(['user_id' => $user->id]);

        $course  = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module  = CourseModule::factory()->create(['course_id' => $course->id, 'tenant_id' => $this->tenant->id]);
        $lesson  = Lesson::factory()->create([
            'course_module_id' => $module->id,
            'tenant_id'        => $this->tenant->id,
            'content_type'     => 'text',
            'is_required'      => true,
        ]);

        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'in_progress',
            'enrolled_at' => now(),
            'started_at'  => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'completed']);
        $this->assertDatabaseHas('certificates', ['enrollment_id' => $enrollment->id]);
    }
}
