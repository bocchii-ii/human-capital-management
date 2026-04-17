<?php

namespace Tests\Unit\Models;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\JobRequisition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Applicant::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Applicant)->getFillable();

        foreach (['tenant_id', 'first_name', 'last_name', 'email', 'phone', 'source'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_full_name_accessor(): void
    {
        $applicant = Applicant::factory()->make(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->assertEquals('Jane Doe', $applicant->full_name);
    }

    public function test_belongs_to_tenant(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $applicant->tenant->id);
    }

    public function test_has_many_applications(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
        $req1      = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $req2      = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $app1      = Application::factory()->create(['tenant_id' => $this->tenant->id, 'applicant_id' => $applicant->id, 'job_requisition_id' => $req1->id]);
        $app2      = Application::factory()->create(['tenant_id' => $this->tenant->id, 'applicant_id' => $applicant->id, 'job_requisition_id' => $req2->id]);

        $this->assertCount(2, $applicant->applications);
        $this->assertTrue($applicant->applications->contains($app1));
        $this->assertTrue($applicant->applications->contains($app2));
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
        $id        = $applicant->id;

        $applicant->delete();

        $this->assertNull(Applicant::find($id));
        $this->assertNotNull(Applicant::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
        $applicant->delete();
        $applicant->restore();

        $this->assertNotNull(Applicant::find($applicant->id));
    }
}
