<?php

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Employee;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class TenantBoundaryTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Interview ─────────────────────────────────────────────────────────────

    public function test_interview_store_rejects_cross_tenant_application(): void
    {
        $otherApp = Application::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [
                'application_id' => $otherApp->id,
                'type'           => 'technical',
                'scheduled_at'   => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertUnprocessable();
    }

    public function test_interview_store_accepts_own_tenant_application(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [
                'application_id' => $app->id,
                'type'           => 'technical',
                'scheduled_at'   => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertCreated();
    }

    // ── Offer ─────────────────────────────────────────────────────────────────

    public function test_offer_store_rejects_cross_tenant_application(): void
    {
        $otherApp = Application::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [
                'application_id' => $otherApp->id,
                'salary'         => 80000,
                'currency'       => 'USD',
                'start_date'     => now()->addDays(30)->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_offer_store_accepts_own_tenant_application(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/offers', [
                'application_id' => $app->id,
                'salary'         => 80000,
                'currency'       => 'USD',
                'start_date'     => now()->addDays(30)->toDateString(),
            ])
            ->assertCreated();
    }

    // ── Onboarding Assignment ─────────────────────────────────────────────────

    public function test_onboarding_assignment_store_rejects_cross_tenant_employee(): void
    {
        $otherEmployee = Employee::factory()->create(); // different tenant
        $template      = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [
                'employee_id'            => $otherEmployee->id,
                'onboarding_template_id' => $template->id,
                'start_date'             => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_onboarding_assignment_store_rejects_cross_tenant_template(): void
    {
        $employee      = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherTemplate = OnboardingTemplate::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [
                'employee_id'            => $employee->id,
                'onboarding_template_id' => $otherTemplate->id,
                'start_date'             => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_onboarding_assignment_store_accepts_own_tenant_resources(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [
                'employee_id'            => $employee->id,
                'onboarding_template_id' => $template->id,
                'start_date'             => now()->addDay()->toDateString(),
            ])
            ->assertCreated();
    }
}
