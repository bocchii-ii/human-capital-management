<?php

namespace Tests\Feature\Api\V1;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\JobRequisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class ApplicationTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeRequisitionAndApplicant(): array
    {
        return [
            JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]),
            Applicant::factory()->create(['tenant_id' => $this->tenant->id]),
        ];
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_applications(): void
    {
        Application::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applications')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_applications(): void
    {
        Application::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Application::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applications');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_stage(): void
    {
        Application::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'stage' => 'screening']);
        Application::factory()->create(['tenant_id' => $this->tenant->id, 'stage' => 'applied']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applications?stage=screening');

        $this->assertCount(2, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_application(): void
    {
        [$req, $applicant] = $this->makeRequisitionAndApplicant();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applications', [
                'job_requisition_id' => $req->id,
                'applicant_id'       => $applicant->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'applied');
    }

    public function test_store_requires_job_requisition_and_applicant(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applications', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_requisition_id', 'applicant_id']);
    }

    public function test_store_denied_without_permission(): void
    {
        [$req, $applicant] = $this->makeRequisitionAndApplicant();
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applications', [
                'job_requisition_id' => $req->id,
                'applicant_id'       => $applicant->id,
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_application_with_relations(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/applications/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $app->id)
            ->assertJsonStructure(['data' => ['applicant', 'job_requisition']]);
    }

    // ── Update Stage ──────────────────────────────────────────────────────────

    public function test_update_stage_changes_application_stage(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id, 'stage' => 'applied']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/applications/{$app->id}/stage", ['stage' => 'screening'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'screening');
    }

    public function test_update_stage_to_rejected_requires_rejection_reason(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/applications/{$app->id}/stage", ['stage' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_update_stage_rejects_invalid_stage(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/applications/{$app->id}/stage", ['stage' => 'invalid_stage'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_application(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/applications/{$app->id}")
            ->assertOk();

        $this->assertSoftDeleted('applications', ['id' => $app->id]);
    }
}
