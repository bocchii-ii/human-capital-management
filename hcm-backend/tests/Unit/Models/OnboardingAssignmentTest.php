<?php

namespace Tests\Unit\Models;

use App\Models\Employee;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskCompletion;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingAssignmentTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(OnboardingAssignment::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new OnboardingAssignment)->getFillable();

        foreach (['tenant_id', 'employee_id', 'onboarding_template_id', 'assigned_by', 'start_date', 'status', 'completed_at'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_start_date_cast_to_date(): void
    {
        $assignment = OnboardingAssignment::factory()->make(['start_date' => '2026-05-01']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $assignment->start_date);
    }

    public function test_completed_at_cast_to_datetime(): void
    {
        $assignment = OnboardingAssignment::factory()->completed()->make();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $assignment->completed_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $assignment->tenant->id);
    }

    public function test_belongs_to_employee(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);

        $this->assertEquals($employee->id, $assignment->employee->id);
    }

    public function test_belongs_to_template(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $this->assertEquals($template->id, $assignment->template->id);
    }

    public function test_has_many_task_completions(): void
    {
        $template   = OnboardingTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $task       = OnboardingTask::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);
        $user       = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'onboarding_template_id' => $template->id]);

        $completion = OnboardingTaskCompletion::create([
            'onboarding_assignment_id' => $assignment->id,
            'onboarding_task_id'       => $task->id,
            'completed_by'             => $user->id,
            'completed_at'             => now(),
        ]);

        $this->assertCount(1, $assignment->taskCompletions);
        $this->assertTrue($assignment->taskCompletions->contains($completion));
    }

    public function test_in_progress_factory_state(): void
    {
        $assignment = OnboardingAssignment::factory()->inProgress()->make();

        $this->assertEquals('in_progress', $assignment->status);
    }

    public function test_completed_factory_state(): void
    {
        $assignment = OnboardingAssignment::factory()->completed()->make();

        $this->assertEquals('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $assignment = OnboardingAssignment::factory()->create(['tenant_id' => $this->tenant->id]);
        $id         = $assignment->id;

        $assignment->delete();

        $this->assertNull(OnboardingAssignment::find($id));
        $this->assertNotNull(OnboardingAssignment::withTrashed()->find($id));
    }
}
