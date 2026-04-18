<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_fillable_fields(): void
    {
        $progress = new LessonProgress();
        $this->assertEqualsCanonicalizing(
            ['tenant_id', 'enrollment_id', 'lesson_id', 'status', 'completed_at'],
            $progress->getFillable()
        );
    }

    public function test_completed_state(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module   = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson   = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $progress = LessonProgress::create([
            'tenant_id'     => $this->tenant->id,
            'enrollment_id' => $enrollment->id,
            'lesson_id'     => $lesson->id,
            'status'        => 'completed',
            'completed_at'  => now(),
        ]);

        $this->assertEquals('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_belongs_to_enrollment(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module   = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson   = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $progress = LessonProgress::create([
            'tenant_id' => $this->tenant->id, 'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id, 'status' => 'not_started',
        ]);

        $this->assertInstanceOf(Enrollment::class, $progress->enrollment);
    }
}
