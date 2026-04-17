<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class DepartmentTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_departments(): void
    {
        Department::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/departments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_departments(): void
    {
        Department::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Department::factory()->count(2)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/departments');

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_active_only(): void
    {
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Department::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/departments?active_only=1');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/departments')
            ->assertUnauthorized();
    }

    // ── Tree ──────────────────────────────────────────────────────────────────

    public function test_tree_returns_hierarchical_departments(): void
    {
        $parent = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/departments/tree');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data); // only root department
        $this->assertNotEmpty($data[0]['children']);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_department(): void
    {
        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/departments', [
                'name' => 'Engineering',
                'code' => 'ENG',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Engineering', 'code' => 'ENG']);

        $this->assertDatabaseHas('departments', ['name' => 'Engineering', 'tenant_id' => $this->tenant->id]);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/departments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_forbidden_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/departments', ['name' => 'Test'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/departments/{$dept->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $dept->id]);
    }

    public function test_show_returns_403_for_other_tenant_department(): void
    {
        $otherDept = Department::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/departments/{$otherDept->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/departments/{$dept->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_update_forbidden_without_permission(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/departments/{$dept->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/departments/{$dept->id}")
            ->assertOk()
            ->assertJson(['message' => 'Department deleted.']);

        $this->assertSoftDeleted('departments', ['id' => $dept->id]);
    }
}
