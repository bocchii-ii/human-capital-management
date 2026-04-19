<?php

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\JobRequisition;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class DashboardTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Overview ──────────────────────────────────────────────────────────────

    public function test_overview_returns_expected_structure(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'employees' => ['total', 'active', 'new_this_month'],
                'hiring'    => ['open_requisitions', 'applications_this_month', 'hires_this_month'],
                'onboarding' => ['pending', 'in_progress', 'completed'],
                'training'  => ['active_enrollments', 'completions_this_month', 'certificates_issued'],
            ]);
    }

    public function test_overview_counts_match_seeded_data(): void
    {
        Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('employees.active', 3);
    }

    public function test_overview_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_overview_forbidden_for_employee_role(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }

    // ── Hiring ────────────────────────────────────────────────────────────────

    public function test_hiring_returns_expected_structure(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/hiring')
            ->assertOk()
            ->assertJsonStructure([
                'requisitions_by_status',
                'applications_by_stage',
                'hires_per_month',
            ]);
    }

    public function test_hiring_counts_requisitions_by_status(): void
    {
        JobRequisition::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'approved']);
        JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/hiring')
            ->assertOk()
            ->assertJsonPath('requisitions_by_status.approved', 2)
            ->assertJsonPath('requisitions_by_status.draft', 1);
    }

    // ── Onboarding ────────────────────────────────────────────────────────────

    public function test_onboarding_returns_expected_structure(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/onboarding')
            ->assertOk()
            ->assertJsonStructure([
                'assignments_by_status',
                'completion_rate',
                'total_assignments',
            ]);
    }

    public function test_onboarding_calculates_completion_rate(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        OnboardingAssignment::factory()->count(4)->create([
            'tenant_id'              => $this->tenant->id,
            'employee_id'            => $employee->id,
            'onboarding_template_id' => $template->id,
            'status'                 => 'completed',
        ]);
        OnboardingAssignment::factory()->create([
            'tenant_id'              => $this->tenant->id,
            'employee_id'            => $employee->id,
            'onboarding_template_id' => $template->id,
            'status'                 => 'pending',
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/onboarding')
            ->assertOk()
            ->assertJsonPath('total_assignments', 5)
            ->assertJsonPath('completion_rate', 80);
    }

    // ── Training ──────────────────────────────────────────────────────────────

    public function test_training_returns_expected_structure(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/training')
            ->assertOk()
            ->assertJsonStructure([
                'enrollments_by_status',
                'completion_rate',
                'total_enrollments',
                'certificates_issued',
                'certificates_this_month',
                'courses_by_category',
            ]);
    }

    public function test_training_counts_certificates(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $enrollment = Enrollment::create([
            'tenant_id'    => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id'    => $course->id, 'status' => 'completed',
            'enrolled_at'  => now(), 'completed_at' => now(),
        ]);

        Certificate::create([
            'tenant_id'          => $this->tenant->id,
            'enrollment_id'      => $enrollment->id,
            'employee_id'        => $employee->id,
            'course_id'          => $course->id,
            'certificate_number' => 'CERT-TEST-001',
            'issued_at'          => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard/training')
            ->assertOk()
            ->assertJsonPath('certificates_issued', 1)
            ->assertJsonPath('certificates_this_month', 1);
    }

    public function test_trainer_can_access_dashboard(): void
    {
        $trainer = $this->userWithRole('Trainer');

        $this->actingAs($trainer, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/dashboard')
            ->assertOk();
    }
}
