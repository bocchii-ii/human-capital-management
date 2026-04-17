<?php

namespace Tests\Feature\Api\V1;

use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class OnboardingTaskTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_tasks(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingTask::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-tasks')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_tasks(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingTask::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);
        OnboardingTask::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/onboarding-tasks');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_template_id(): void
    {
        $template1 = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $template2 = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        OnboardingTask::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template1->id]);
        OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template2->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-tasks?onboarding_template_id={$template1->id}");

        $this->assertCount(2, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_task(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-tasks', [
                'onboarding_template_id' => $template->id,
                'title'                  => 'Complete paperwork',
                'assignee_role'          => 'hr',
                'due_days_offset'        => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.assignee_role', 'hr');
    }

    public function test_store_requires_template_title_and_assignee_role(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-tasks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['onboarding_template_id', 'title', 'assignee_role']);
    }

    public function test_store_rejects_invalid_assignee_role(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-tasks', [
                'onboarding_template_id' => $template->id,
                'title'                  => 'Task',
                'assignee_role'          => 'ceo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_role']);
    }

    public function test_store_denied_without_permission(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $user     = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/onboarding-tasks', [
                'onboarding_template_id' => $template->id,
                'title'                  => 'Task',
                'assignee_role'          => 'hr',
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_task(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task     = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $task = OnboardingTask::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/onboarding-tasks/{$task->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_task(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task     = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/onboarding-tasks/{$task->id}", ['title' => 'Updated Task Title'])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_tasks', ['id' => $task->id, 'title' => 'Updated Task Title']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_task(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task     = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/onboarding-tasks/{$task->id}")
            ->assertOk();

        $this->assertSoftDeleted('onboarding_tasks', ['id' => $task->id]);
    }
}
