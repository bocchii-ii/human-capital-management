<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class JobRequisitionTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_requisitions(): void
    {
        JobRequisition::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/job-requisitions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_requisitions(): void
    {
        JobRequisition::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        JobRequisition::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/job-requisitions');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        JobRequisition::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
        JobRequisition::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/job-requisitions?status=open');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/job-requisitions')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_a_draft_requisition(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/job-requisitions', [
                'title'           => 'Senior Engineer',
                'employment_type' => 'full_time',
                'headcount'       => 2,
                'currency'        => 'USD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Senior Engineer');
    }

    public function test_store_requires_title(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/job-requisitions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_denied_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/job-requisitions', ['title' => 'Test'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_requisition(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/job-requisitions/{$req->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $req->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $req = JobRequisition::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/job-requisitions/{$req->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_requisition(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/job-requisitions/{$req->id}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function test_approve_transitions_draft_to_approved(): void
    {
        $req = JobRequisition::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/job-requisitions/{$req->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_approve_rejects_non_draft_requisition(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/job-requisitions/{$req->id}/approve")
            ->assertUnprocessable();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_requisition(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/job-requisitions/{$req->id}")
            ->assertOk();

        $this->assertSoftDeleted('job_requisitions', ['id' => $req->id]);
    }
}
