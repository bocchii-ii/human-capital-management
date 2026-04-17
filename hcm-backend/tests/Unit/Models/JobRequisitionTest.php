<?php

namespace Tests\Unit\Models;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\JobRequisition;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobRequisitionTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(JobRequisition::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new JobRequisition)->getFillable();

        foreach (['tenant_id', 'title', 'status', 'employment_type', 'headcount', 'salary_min', 'salary_max', 'currency'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_remote_cast_to_boolean(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'is_remote' => true]);

        $this->assertTrue($req->is_remote);
    }

    public function test_approved_at_cast_to_datetime(): void
    {
        $req = JobRequisition::factory()->approved()->create(['tenant_id' => $this->tenant->id]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $req->approved_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $req->tenant->id);
    }

    public function test_belongs_to_department(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $req  = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $dept->id]);

        $this->assertEquals($dept->id, $req->department->id);
    }

    public function test_belongs_to_position(): void
    {
        $pos = Position::factory()->create(['tenant_id' => $this->tenant->id]);
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'position_id' => $pos->id]);

        $this->assertEquals($pos->id, $req->position->id);
    }

    public function test_belongs_to_hiring_manager(): void
    {
        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $req     = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id, 'hiring_manager_id' => $manager->id]);

        $this->assertEquals($manager->id, $req->hiringManager->id);
    }

    public function test_has_many_applications(): void
    {
        $req  = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $app1 = Application::factory()->create(['tenant_id' => $this->tenant->id, 'job_requisition_id' => $req->id]);
        $app2 = Application::factory()->create(['tenant_id' => $this->tenant->id, 'job_requisition_id' => $req->id]);

        $this->assertCount(2, $req->applications);
        $this->assertTrue($req->applications->contains($app1));
        $this->assertTrue($req->applications->contains($app2));
    }

    public function test_draft_factory_state(): void
    {
        $req = JobRequisition::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals('draft', $req->status);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $id  = $req->id;

        $req->delete();

        $this->assertNull(JobRequisition::find($id));
        $this->assertNotNull(JobRequisition::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $req = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $req->delete();
        $req->restore();

        $this->assertNotNull(JobRequisition::find($req->id));
    }
}
