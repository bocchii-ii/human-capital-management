<?php

namespace Tests\Unit\Models;

use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTemplateTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(OnboardingTemplate::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new OnboardingTemplate)->getFillable();

        foreach (['tenant_id', 'title', 'description', 'is_active', 'department_id', 'position_id'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $template = OnboardingTemplate::factory()->make(['is_active' => 1]);

        $this->assertIsBool($template->is_active);
    }

    public function test_belongs_to_tenant(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $template->tenant->id);
    }

    public function test_has_many_tasks(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task1    = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);
        $task2    = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->assertCount(2, $template->tasks);
        $this->assertTrue($template->tasks->contains($task1));
        $this->assertTrue($template->tasks->contains($task2));
    }

    public function test_has_many_assignments(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->assertCount(1, $template->assignments);
        $this->assertTrue($template->assignments->contains($assignment));
    }

    public function test_inactive_factory_state(): void
    {
        $template = OnboardingTemplate::factory()->inactive()->make();

        $this->assertFalse($template->is_active);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $id       = $template->id;

        $template->delete();

        $this->assertNull(OnboardingTemplate::find($id));
        $this->assertNotNull(OnboardingTemplate::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $template->delete();
        $template->restore();

        $this->assertNotNull(OnboardingTemplate::find($template->id));
    }
}
