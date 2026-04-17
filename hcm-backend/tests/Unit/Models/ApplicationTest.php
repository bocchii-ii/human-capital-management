<?php

namespace Tests\Unit\Models;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Interview;
use App\Models\JobRequisition;
use App\Models\Offer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private JobRequisition $req;
    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant    = Tenant::factory()->create();
        $this->req       = JobRequisition::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->applicant = Applicant::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeApp(array $attrs = []): Application
    {
        return Application::factory()->create(array_merge([
            'tenant_id'          => $this->tenant->id,
            'job_requisition_id' => $this->req->id,
            'applicant_id'       => $this->applicant->id,
        ], $attrs));
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Application::class));
    }

    public function test_stages_constant_contains_expected_values(): void
    {
        $expected = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

        $this->assertEquals($expected, Application::STAGES);
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Application)->getFillable();

        foreach (['tenant_id', 'job_requisition_id', 'applicant_id', 'stage', 'stage_changed_at'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_stage_changed_at_cast_to_datetime(): void
    {
        $app = $this->makeApp();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $app->stage_changed_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $app = $this->makeApp();

        $this->assertEquals($this->tenant->id, $app->tenant->id);
    }

    public function test_belongs_to_job_requisition(): void
    {
        $app = $this->makeApp();

        $this->assertEquals($this->req->id, $app->jobRequisition->id);
    }

    public function test_belongs_to_applicant(): void
    {
        $app = $this->makeApp();

        $this->assertEquals($this->applicant->id, $app->applicant->id);
    }

    public function test_has_many_interviews(): void
    {
        $app  = $this->makeApp();
        $int1 = Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);
        $int2 = Interview::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->assertCount(2, $app->interviews);
        $this->assertTrue($app->interviews->contains($int1));
        $this->assertTrue($app->interviews->contains($int2));
    }

    public function test_has_one_offer(): void
    {
        $app   = $this->makeApp();
        $offer = Offer::factory()->create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id]);

        $this->assertEquals($offer->id, $app->offer->id);
    }

    public function test_rejected_factory_state(): void
    {
        $app = Application::factory()->rejected()->create([
            'tenant_id'          => $this->tenant->id,
            'job_requisition_id' => $this->req->id,
            'applicant_id'       => $this->applicant->id,
        ]);

        $this->assertEquals('rejected', $app->stage);
        $this->assertNotNull($app->rejection_reason);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $app = $this->makeApp();
        $id  = $app->id;

        $app->delete();

        $this->assertNull(Application::find($id));
        $this->assertNotNull(Application::withTrashed()->find($id));
    }
}
