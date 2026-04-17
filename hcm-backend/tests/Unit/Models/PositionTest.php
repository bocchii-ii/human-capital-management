<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Position::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Position)->getFillable();

        foreach (['tenant_id', 'department_id', 'title', 'description', 'level', 'is_active'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertIsBool($position->is_active);
    }

    public function test_belongs_to_tenant(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $position->tenant->id);
    }

    public function test_belongs_to_department(): void
    {
        $dept     = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $this->assertEquals($dept->id, $position->department->id);
    }

    public function test_has_many_employees(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);
        Employee::factory()->count(4)->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id]);

        $this->assertCount(4, $position->employees);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);
        $id = $position->id;

        $position->delete();

        $this->assertNull(Position::find($id));
        $this->assertNotNull(Position::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);
        $position->delete();
        $position->restore();

        $this->assertNotNull(Position::find($position->id));
    }
}
