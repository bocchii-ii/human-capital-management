<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class AuditLogTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeLog(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->adminUser->id,
            'event'     => 'user.login',
        ], $overrides));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_logs(): void
    {
        $this->makeLog();
        $this->makeLog(['event' => 'offer.sent']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_event(): void
    {
        $this->makeLog(['event' => 'user.login']);
        $this->makeLog(['event' => 'offer.sent']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/audit-logs?event=user.login')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'user.login');
    }

    public function test_index_filters_by_user_id(): void
    {
        $other = $this->userWithRole('Employee');
        $this->makeLog(['user_id' => $this->adminUser->id]);
        $this->makeLog(['user_id' => $other->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/audit-logs?user_id={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_does_not_leak_other_tenant_logs(): void
    {
        $this->makeLog();

        // Create log for another tenant
        $other = \App\Models\Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id]);
        AuditLog::create(['tenant_id' => $other->id, 'user_id' => $otherUser->id, 'event' => 'user.login']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
    }

    public function test_index_forbidden_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    // ── Integration: audit written on key events ──────────────────────────────

    public function test_audit_written_on_application_stage_change(): void
    {
        $applicant = \App\Models\Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
        $req       = \App\Models\JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'approved']);
        $app       = \App\Models\Application::create([
            'tenant_id'          => $this->tenant->id,
            'job_requisition_id' => $req->id,
            'applicant_id'       => $applicant->id,
            'stage'              => 'applied',
            'stage_changed_at'   => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/applications/{$app->id}/stage", ['stage' => 'screening'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id'      => $this->tenant->id,
            'event'          => 'application.stage_changed',
            'auditable_type' => \App\Models\Application::class,
            'auditable_id'   => $app->id,
        ]);
    }

    public function test_audit_written_on_offer_sent(): void
    {
        $applicant = \App\Models\Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
        $req       = \App\Models\JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'approved']);
        $app       = \App\Models\Application::create([
            'tenant_id'          => $this->tenant->id,
            'job_requisition_id' => $req->id,
            'applicant_id'       => $applicant->id,
            'stage'              => 'interview',
            'stage_changed_at'   => now(),
        ]);
        $offer = \App\Models\Offer::create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $app->id,
            'salary'         => 60000,
            'start_date'     => now()->addMonth(),
            'status'         => 'draft',
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/offers/{$offer->id}/send")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'event'     => 'offer.sent',
        ]);
    }
}
