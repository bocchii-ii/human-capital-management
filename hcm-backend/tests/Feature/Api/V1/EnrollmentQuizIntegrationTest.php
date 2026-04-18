<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class EnrollmentQuizIntegrationTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    private function makeQuizLesson(): array
    {
        $course  = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module  = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson  = Lesson::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'course_module_id' => $module->id,
            'content_type'     => 'quiz',
            'is_required'      => true,
        ]);
        $quiz = Quiz::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'lesson_id'      => $lesson->id,
            'pass_threshold' => 50,
        ]);
        $question = Question::factory()->singleChoice()->create([
            'tenant_id' => $this->tenant->id,
            'quiz_id'   => $quiz->id,
            'points'    => 1,
        ]);
        $correct = QuestionOption::factory()->correct()->create([
            'tenant_id'   => $this->tenant->id,
            'question_id' => $question->id,
        ]);
        $wrong = QuestionOption::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'question_id' => $question->id,
            'is_correct'  => false,
        ]);

        return compact('course', 'module', 'lesson', 'quiz', 'question', 'correct', 'wrong');
    }

    public function test_passing_quiz_attempt_auto_marks_lesson_completed_and_recomputes_enrollment(): void
    {
        $data       = $this->makeQuizLesson();
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user       = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee->update(['user_id' => $user->id]);
        $user->assignRole('Employee');

        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $data['course']->id,
            'status'      => 'in_progress',
            'enrolled_at' => now(),
            'started_at'  => now(),
        ]);

        // Start a quiz attempt
        $attemptResp = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $data['quiz']->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $attemptId = $attemptResp->json('data.id');

        // Submit correct answer
        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attemptId}/submit", [
                'answers' => [
                    [
                        'question_id'         => $data['question']->id,
                        'selected_option_ids' => [$data['correct']->id],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', true);

        // Lesson progress should be created automatically
        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id'     => $data['lesson']->id,
            'status'        => 'completed',
        ]);

        // Enrollment should be completed (only 1 required lesson, now done)
        $this->assertEquals('completed', $enrollment->fresh()->status);
        $this->assertEquals(100, (int) $enrollment->fresh()->progress_percentage);
    }

    public function test_failing_quiz_attempt_does_not_advance_lesson_progress(): void
    {
        $data       = $this->makeQuizLesson();
        $employee   = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user       = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee->update(['user_id' => $user->id]);
        $user->assignRole('Employee');

        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $data['course']->id,
            'status'      => 'in_progress',
            'enrolled_at' => now(),
            'started_at'  => now(),
        ]);

        $attemptResp = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $data['quiz']->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $attemptId = $attemptResp->json('data.id');

        // Submit wrong answer
        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attemptId}/submit", [
                'answers' => [
                    [
                        'question_id'         => $data['question']->id,
                        'selected_option_ids' => [$data['wrong']->id],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', false);

        // No lesson_progress row created
        $this->assertDatabaseMissing('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id'     => $data['lesson']->id,
            'status'        => 'completed',
        ]);

        // Enrollment still in_progress
        $this->assertEquals('in_progress', $enrollment->fresh()->status);
    }

    public function test_passing_quiz_without_enrollment_does_not_error(): void
    {
        $data     = $this->makeQuizLesson();
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user     = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee->update(['user_id' => $user->id]);
        $user->assignRole('Employee');

        // No enrollment created — attempt should still work and not throw
        $attemptResp = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $data['quiz']->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $attemptId = $attemptResp->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attemptId}/submit", [
                'answers' => [
                    [
                        'question_id'         => $data['question']->id,
                        'selected_option_ids' => [$data['correct']->id],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', true);

        $this->assertDatabaseCount('lesson_progress', 0);
    }
}
