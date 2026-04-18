<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_fillable_fields(): void
    {
        $enrollment = new Enrollment();
        $this->assertEqualsCanonicalizing([
            'tenant_id', 'employee_id', 'course_id', 'learning_path_id', 'enrolled_by',
            'status', 'progress_percentage', 'enrolled_at', 'started_at', 'completed_at', 'due_date',
        ], $enrollment->getFillable());
    }

    public function test_enrolled_state(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->assertEquals('enrolled', $enrollment->status);
        $this->assertNull($enrollment->started_at);
        $this->assertNull($enrollment->completed_at);
    }

    public function test_progress_percentage_cast(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $enrollment = Enrollment::create([
            'tenant_id'           => $this->tenant->id,
            'employee_id'         => $employee->id,
            'course_id'           => $course->id,
            'status'              => 'enrolled',
            'enrolled_at'         => now(),
            'progress_percentage' => 50.50,
        ]);

        $this->assertIsFloat($enrollment->progress_percentage);
        $this->assertEquals(50.50, $enrollment->progress_percentage);
    }

    public function test_belongs_to_tenant(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->assertInstanceOf(Tenant::class, $enrollment->tenant);
    }

    public function test_has_many_lesson_progress(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->assertCount(0, $enrollment->lessonProgress);
    }
}
