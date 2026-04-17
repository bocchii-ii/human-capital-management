<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Employee::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Employee)->getFillable();

        foreach (['tenant_id', 'user_id', 'department_id', 'position_id', 'manager_id',
            'first_name', 'last_name', 'email', 'employee_number', 'hire_date',
            'employment_type', 'status', 'work_location'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_full_name_accessor(): void
    {
        $emp = Employee::factory()->make(['first_name' => 'John', 'last_name' => 'Smith']);

        $this->assertEquals('John Smith', $emp->full_name);
    }

    public function test_hire_date_cast_to_date(): void
    {
        $emp = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hire_date' => '2024-01-15',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emp->hire_date);
        $this->assertEquals('2024-01-15', $emp->hire_date->toDateString());
    }

    public function test_termination_date_cast_to_date(): void
    {
        $emp = Employee::factory()->terminated()->create(['tenant_id' => $this->tenant->id]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emp->termination_date);
    }

    public function test_belongs_to_tenant(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $emp->tenant->id);
    }

    public function test_belongs_to_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $emp  = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $this->assertEquals($dept->id, $emp->department->id);
    }

    public function test_belongs_to_position(): void
    {
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);
        $emp      = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id]);

        $this->assertEquals($position->id, $emp->position->id);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $emp  = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        $this->assertEquals($user->id, $emp->user->id);
    }

    public function test_manager_and_direct_reports_relationship(): void
    {
        $manager = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $report1 = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'manager_id' => $manager->id]);
        $report2 = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'manager_id' => $manager->id]);

        $this->assertEquals($manager->id, $report1->manager->id);
        $this->assertCount(2, $manager->directReports);
        $this->assertTrue($manager->directReports->contains($report2));
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $id  = $emp->id;

        $emp->delete();

        $this->assertNull(Employee::find($id));
        $this->assertNotNull(Employee::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $emp = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $emp->delete();
        $emp->restore();

        $this->assertNotNull(Employee::find($emp->id));
    }

    public function test_terminated_factory_state(): void
    {
        $emp = Employee::factory()->terminated()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals('terminated', $emp->status);
        $this->assertNotNull($emp->termination_date);
    }
}
