<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
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

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_enrollments(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_non_manager_sees_own_enrollments_only(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $other    = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $course2 = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $other->id,
            'course_id' => $course2->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/enrollments')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_enrollment(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'enrolled');
        $this->assertDatabaseHas('enrollments', [
            'employee_id'         => $employee->id,
            'status'              => 'enrolled',
            'progress_percentage' => 0,
        ]);
    }

    public function test_store_rejects_unpublished_course(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
            ])
            ->assertUnprocessable();
    }

    public function test_store_rejects_duplicate_active_enrollment(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
            ])
            ->assertUnprocessable();
    }

    public function test_store_reactivates_withdrawn_enrollment(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'withdrawn', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'enrolled');

        $this->assertCount(1, Enrollment::where('employee_id', $employee->id)->get());
    }

    public function test_self_enroll_succeeds_for_own_employee(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
            ])
            ->assertCreated();
    }

    public function test_self_enroll_fails_for_other_employee(): void
    {
        $user     = $this->userWithRole('Employee');
        $other    = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $other->id,
                'course_id'   => $course->id,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_employee_from_other_tenant(): void
    {
        $otherEmp = Employee::factory()->create();
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/enrollments', [
                'employee_id' => $otherEmp->id,
                'course_id'   => $course->id,
            ])
            ->assertUnprocessable();
    }

    // ── Start ─────────────────────────────────────────────────────────────────

    public function test_start_transitions_to_in_progress(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertNotNull($enrollment->fresh()->started_at);
    }

    public function test_start_rejects_non_enrolled_status(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'in_progress', 'enrolled_at' => now(),
            'started_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/start")
            ->assertUnprocessable();
    }

    public function test_start_by_employee_owner(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/start")
            ->assertOk();
    }

    public function test_start_forbidden_for_other_employee(): void
    {
        $user     = $this->userWithRole('Employee');
        $other    = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course   = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $other->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/start")
            ->assertForbidden();
    }

    // ── Withdraw ──────────────────────────────────────────────────────────────

    public function test_withdraw_transitions_to_withdrawn(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'in_progress', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_withdraw_rejects_completed_enrollment(): void
    {
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $course     = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'completed', 'enrolled_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw")
            ->assertUnprocessable();
    }

    // ── CompleteLesson ────────────────────────────────────────────────────────

    private function makeCourseWithLesson(bool $isQuizLesson = false): array
    {
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson = Lesson::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'course_module_id' => $module->id,
            'content_type'     => $isQuizLesson ? 'quiz' : 'text',
            'is_required'      => true,
        ]);

        return [$course, $module, $lesson];
    }

    public function test_complete_lesson_marks_progress_and_recomputes(): void
    {
        [$course, , $lesson] = $this->makeCourseWithLesson();
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.progress_percentage', 100);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id'     => $lesson->id,
            'status'        => 'completed',
        ]);
        $this->assertEquals('completed', $enrollment->fresh()->status);
    }

    public function test_complete_lesson_auto_advances_to_in_progress(): void
    {
        $course  = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module  = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson1 = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id, 'is_required' => true]);
        $lesson2 = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id, 'is_required' => true]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson1->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertEqualsWithDelta(50.0, $enrollment->fresh()->progress_percentage, 0.01);
    }

    public function test_complete_lesson_rejects_lesson_from_other_course(): void
    {
        [$course, ,] = $this->makeCourseWithLesson();
        $otherModule = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherLesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $otherModule->id]);
        $employee    = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment  = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$otherLesson->id}/complete")
            ->assertUnprocessable();
    }

    public function test_complete_quiz_lesson_without_passed_attempt_fails(): void
    {
        [$course, , $lesson] = $this->makeCourseWithLesson(isQuizLesson: true);
        Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id]);
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertUnprocessable();
    }

    public function test_complete_quiz_lesson_with_passed_attempt_succeeds(): void
    {
        [$course, , $lesson] = $this->makeCourseWithLesson(isQuizLesson: true);
        $quiz     = Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        // Seed a passing attempt
        QuizAttempt::create([
            'tenant_id'        => $this->tenant->id,
            'quiz_id'          => $quiz->id,
            'employee_id'      => $employee->id,
            'attempt_number'   => 1,
            'status'           => 'submitted',
            'passed'           => true,
            'score_percentage' => 100,
            'started_at'       => now(),
            'submitted_at'     => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_complete_lesson_forbidden_for_other_employee(): void
    {
        [$course, , $lesson] = $this->makeCourseWithLesson();
        $user     = $this->userWithRole('Employee');
        $other    = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $other->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertForbidden();
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_show_forbidden_for_other_tenant(): void
    {
        $employee   = Employee::factory()->create();
        $course     = Course::factory()->published()->create(['tenant_id' => $employee->tenant_id]);
        $enrollment = Enrollment::create([
            'tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id,
            'course_id' => $course->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/enrollments/{$enrollment->id}")
            ->assertForbidden();
    }
}
