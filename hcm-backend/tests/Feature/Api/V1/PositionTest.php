<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class PositionTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_positions(): void
    {
        Position::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/positions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_positions(): void
    {
        Position::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Position::factory()->count(3)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/positions');

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_department(): void
    {
        $dept  = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $other = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        Position::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);
        Position::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $other->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/positions?department_id={$dept->id}");

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_active_only(): void
    {
        Position::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Position::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/positions?active_only=1');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/positions')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_position(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/positions', [
                'title'         => 'Software Engineer',
                'department_id' => $dept->id,
                'level'         => 'mid',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Software Engineer', 'level' => 'mid']);

        $this->assertDatabaseHas('positions', ['title' => 'Software Engineer', 'tenant_id' => $this->tenant->id]);
    }

    public function test_store_requires_title(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/positions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_store_forbidden_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/positions', ['title' => 'Test'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_position_with_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $pos  = Position::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/positions/{$pos->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $pos->id])
            ->assertJsonPath('data.department.id', $dept->id);
    }

    public function test_show_returns_403_for_other_tenant(): void
    {
        $otherPos = Position::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/positions/{$otherPos->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_position(): void
    {
        $pos = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/positions/{$pos->id}", ['title' => 'Senior Engineer', 'level' => 'senior'])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Senior Engineer']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_position(): void
    {
        $pos = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/positions/{$pos->id}")
            ->assertOk();

        $this->assertSoftDeleted('positions', ['id' => $pos->id]);
    }
}
