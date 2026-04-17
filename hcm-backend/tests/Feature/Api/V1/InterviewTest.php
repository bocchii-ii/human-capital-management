<?php

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class InterviewTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_interviews(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        Interview::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/interviews')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_interviews(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        Interview::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);
        Interview::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/interviews');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_application_id(): void
    {
        $app1 = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $app2 = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        Interview::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'application_id' => $app1->id]);
        Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app2->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/interviews?application_id={$app1->id}");

        $this->assertCount(2, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_interview(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [
                'application_id' => $app->id,
                'type'           => 'technical',
                'scheduled_at'   => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'technical');
    }

    public function test_store_requires_application_type_and_scheduled_at(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['application_id', 'type', 'scheduled_at']);
    }

    public function test_store_rejects_past_scheduled_at(): void
    {
        $app = Application::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [
                'application_id' => $app->id,
                'type'           => 'hr',
                'scheduled_at'   => now()->subDay()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_store_denied_without_permission(): void
    {
        $app  = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/interviews', [
                'application_id' => $app->id,
                'type'           => 'hr',
                'scheduled_at'   => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_interview(): void
    {
        $app       = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $interview = Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/interviews/{$interview->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $interview->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $interview = Interview::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/interviews/{$interview->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_records_interview_result(): void
    {
        $app       = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $interview = Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/interviews/{$interview->id}", [
                'result'   => 'pass',
                'feedback' => 'Great candidate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'pass');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_interview(): void
    {
        $app       = Application::factory()->create(['tenant_id' => $this->tenant->id]);
        $interview = Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/interviews/{$interview->id}")
            ->assertOk();

        $this->assertSoftDeleted('interviews', ['id' => $interview->id]);
    }
}
