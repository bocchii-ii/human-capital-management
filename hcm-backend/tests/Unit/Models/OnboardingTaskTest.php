<?php

namespace Tests\Unit\Models;

use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTaskTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(OnboardingTask::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new OnboardingTask)->getFillable();

        foreach (['tenant_id', 'onboarding_template_id', 'title', 'assignee_role', 'due_days_offset', 'is_required', 'sort_order'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_casts(): void
    {
        $task = OnboardingTask::factory()->make(['is_required' => 1, 'due_days_offset' => '5', 'sort_order' => '10']);

        $this->assertIsBool($task->is_required);
        $this->assertIsInt($task->due_days_offset);
        $this->assertIsInt($task->sort_order);
    }

    public function test_belongs_to_tenant(): void
    {
        $task = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $task->tenant->id);
    }

    public function test_belongs_to_template(): void
    {
        $template = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task     = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->assertEquals($template->id, $task->template->id);
    }

    public function test_optional_factory_state(): void
    {
        $task = OnboardingTask::factory()->optional()->make();

        $this->assertFalse($task->is_required);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $task = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id]);
        $id   = $task->id;

        $task->delete();

        $this->assertNull(OnboardingTask::find($id));
        $this->assertNotNull(OnboardingTask::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $task = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id]);
        $task->delete();
        $task->restore();

        $this->assertNotNull(OnboardingTask::find($task->id));
    }
}
