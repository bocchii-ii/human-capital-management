<?php

namespace Tests\Feature\Api\V1;

use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class ApplicantTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_applicants(): void
    {
        Applicant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applicants')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_applicants(): void
    {
        Applicant::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Applicant::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applicants');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_searches_by_name_and_email(): void
    {
        Applicant::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com']);
        Applicant::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'bob@example.com']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applicants?search=Alice');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/applicants')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_applicant(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applicants', [
                'first_name' => 'Jane',
                'last_name'  => 'Doe',
                'email'      => 'jane.doe@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.last_name', 'Doe');
    }

    public function test_store_requires_first_and_last_name_and_email(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applicants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    public function test_store_denied_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/applicants', [
                'first_name' => 'Jane',
                'last_name'  => 'Doe',
                'email'      => 'jane@example.com',
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_applicant(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/applicants/{$applicant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $applicant->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $applicant = Applicant::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/applicants/{$applicant->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_applicant(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/applicants/{$applicant->id}", ['first_name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Updated');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_applicant(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/applicants/{$applicant->id}")
            ->assertOk();

        $this->assertSoftDeleted('applicants', ['id' => $applicant->id]);
    }
}
