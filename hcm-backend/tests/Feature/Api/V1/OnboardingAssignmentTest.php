<?php

namespace Tests\Feature\Api\V1;

use App\Models\Employee;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class OnboardingAssignmentTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_assignments(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingAssignment::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-assignments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_assignments(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingAssignment::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);
        OnboardingAssignment::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-assignments');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingAssignment::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id, 'status' => 'pending']);
        OnboardingAssignment::factory()->inProgress()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-assignments?status=pending');

        $this->assertCount(2, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_assignment(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [
                'employee_id'            => $employee->id,
                'onboarding_template_id' => $template->id,
                'start_date'             => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_store_requires_employee_template_and_start_date(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'onboarding_template_id', 'start_date']);
    }

    public function test_store_denied_without_permission(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user     = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-assignments', [
                'employee_id'            => $employee->id,
                'onboarding_template_id' => $template->id,
                'start_date'             => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_assignment_with_relations(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignment->id)
            ->assertJsonStructure(['data' => ['employee', 'template', 'task_completions']]);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $assignment = OnboardingAssignment::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-assignments/{$assignment->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_changes_status(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/onboarding-assignments/{$assignment->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_update_sets_completed_at_when_status_completed(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/onboarding-assignments/{$assignment->id}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_assignments', ['id' => $assignment->id, 'status' => 'completed']);
        $this->assertNotNull($assignment->fresh()->completed_at);
    }

    // ── Complete Task ─────────────────────────────────────────────────────────

    public function test_complete_task_records_completion(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task       = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/onboarding-assignments/{$assignment->id}/tasks/{$task->id}/complete", ['notes' => 'Done!'])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_task_completions', [
            'onboarding_assignment_id' => $assignment->id,
            'onboarding_task_id'       => $task->id,
        ]);
    }

    public function test_complete_task_advances_status_to_in_progress(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task       = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id, 'status' => 'pending']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/onboarding-assignments/{$assignment->id}/tasks/{$task->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('onboarding_assignments', ['id' => $assignment->id, 'status' => 'in_progress']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_assignment(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id, 'assigned_by' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/onboarding-assignments/{$assignment->id}")
            ->assertOk();

        $this->assertSoftDeleted('onboarding_assignments', ['id' => $assignment->id]);
    }
}
