<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class EmployeeTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_employees(): void
    {
        Employee::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_current_tenant_employees(): void
    {
        Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Employee::factory()->count(2)->create(); // other tenant

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees');

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Employee::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);
        Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/employees?department_id={$dept->id}");

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        Employee::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        Employee::factory()->terminated()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees?status=terminated');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_searches_by_name(): void
    {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Alice', 'last_name' => 'Wonder']);
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Bob', 'last_name' => 'Builder']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees?search=Alice');

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alice', $response->json('data.0.first_name'));
    }

    public function test_index_searches_by_employee_number(): void
    {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'EMP-9999']);
        Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees?search=EMP-9999');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    // ── Org Chart ─────────────────────────────────────────────────────────────

    public function test_org_chart_returns_root_employees_with_reports(): void
    {
        $ceo     = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'manager_id' => $ceo->id, 'status' => 'active']);
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'manager_id' => $manager->id, 'status' => 'active']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees/org-chart');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data); // only CEO at root
        $this->assertNotEmpty($data[0]['direct_reports']);
    }

    public function test_org_chart_excludes_inactive_employees(): void
    {
        Employee::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/employees/org-chart');

        $this->assertCount(1, $response->json('data'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_employee(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $pos  = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/employees', [
                'first_name'      => 'Jane',
                'last_name'       => 'Doe',
                'email'           => 'jane.doe@example.com',
                'department_id'   => $dept->id,
                'position_id'     => $pos->id,
                'employee_number' => 'EMP-0001',
                'hire_date'       => '2024-01-15',
                'employment_type' => 'full_time',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['first_name' => 'Jane', 'last_name' => 'Doe'])
            ->assertJsonPath('data.full_name', 'Jane Doe');

        $this->assertDatabaseHas('employees', [
            'first_name' => 'Jane',
            'tenant_id'  => $this->tenant->id,
            'status'     => 'active',
        ]);
    }

    public function test_store_requires_first_and_last_name(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/employees', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_store_validates_employment_type(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/employees', [
                'first_name'      => 'Jane',
                'last_name'       => 'Doe',
                'employment_type' => 'invalid_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employment_type');
    }

    public function test_store_forbidden_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/employees', ['first_name' => 'Jane', 'last_name' => 'Doe'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_employee_with_relations(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $emp  = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/employees/{$emp->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $emp->id])
            ->assertJsonPath('data.department.id', $dept->id);
    }

    public function test_show_returns_403_for_other_tenant(): void
    {
        $other = Employee::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/employees/{$other->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_employee(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/employees/{$emp->id}", [
                'first_name' => 'Updated',
                'status'     => 'inactive',
            ])
            ->assertOk()
            ->assertJsonFragment(['first_name' => 'Updated', 'status' => 'inactive']);
    }

    public function test_update_validates_status_values(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/employees/{$emp->id}", ['status' => 'flying'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_forbidden_without_permission(): void
    {
        $emp  = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->patchJson("/api/v1/employees/{$emp->id}", ['first_name' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_employee(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/employees/{$emp->id}")
            ->assertOk();

        $this->assertSoftDeleted('employees', ['id' => $emp->id]);
    }

    public function test_destroy_forbidden_without_permission(): void
    {
        $emp  = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/employees/{$emp->id}")
            ->assertForbidden();
    }
}
