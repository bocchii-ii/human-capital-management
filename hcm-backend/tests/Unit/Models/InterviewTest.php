<?php

namespace Tests\Unit\Models;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Interview;
use App\Models\JobRequisition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant      = Tenant::factory()->create();
        $this->application = Application::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Interview::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Interview)->getFillable();

        foreach (['tenant_id', 'application_id', 'interviewer_id', 'type', 'scheduled_at', 'duration_minutes', 'result'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_scheduled_at_cast_to_datetime(): void
    {
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
            'scheduled_at'   => '2026-05-01 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $interview->scheduled_at);
        $this->assertEquals('2026-05-01', $interview->scheduled_at->toDateString());
    }

    public function test_belongs_to_tenant(): void
    {
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals($this->tenant->id, $interview->tenant->id);
    }

    public function test_belongs_to_application(): void
    {
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals($this->application->id, $interview->application->id);
    }

    public function test_belongs_to_interviewer(): void
    {
        $user      = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
            'interviewer_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $interview->interviewer->id);
    }

    public function test_passed_factory_state(): void
    {
        $interview = Interview::factory()->passed()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals('pass', $interview->result);
        $this->assertNotNull($interview->feedback);
    }

    public function test_failed_factory_state(): void
    {
        $interview = Interview::factory()->failed()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);

        $this->assertEquals('fail', $interview->result);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);
        $id = $interview->id;

        $interview->delete();

        $this->assertNull(Interview::find($id));
        $this->assertNotNull(Interview::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $interview = Interview::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'application_id' => $this->application->id,
        ]);
        $interview->delete();
        $interview->restore();

        $this->assertNotNull(Interview::find($interview->id));
    }
}
