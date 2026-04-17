<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Department::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Department)->getFillable();

        foreach (['tenant_id', 'parent_id', 'name', 'code', 'description', 'is_active'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertIsBool($dept->is_active);
    }

    public function test_belongs_to_tenant(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $dept->tenant->id);
    }

    public function test_self_referential_parent_child_relationship(): void
    {
        $parent = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $child  = Department::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_has_many_positions(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Position::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $this->assertCount(2, $dept->positions);
    }

    public function test_has_many_employees(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $this->assertCount(3, $dept->employees);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $id = $dept->id;

        $dept->delete();

        $this->assertNull(Department::find($id));
        $this->assertNotNull(Department::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $dept->delete();
        $dept->restore();

        $this->assertNotNull(Department::find($dept->id));
    }
}
