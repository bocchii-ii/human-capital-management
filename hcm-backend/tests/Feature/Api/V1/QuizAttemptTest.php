<?php

namespace Tests\Feature\Api\V1;

use App\Models\Employee;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeQuizWithQuestions(): array
    {
        $quiz = Quiz::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'pass_threshold' => 60,
        ]);

        $q1 = Question::factory()->singleChoice()->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz->id, 'points' => 1]);
        $correct1 = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q1->id]);
        QuestionOption::factory()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q1->id]);

        $q2 = Question::factory()->multipleChoice()->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz->id, 'points' => 1]);
        $correct2a = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q2->id]);
        $correct2b = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q2->id]);
        QuestionOption::factory()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q2->id]);

        $q3 = Question::factory()->trueFalse()->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz->id, 'points' => 1]);
        QuestionOption::factory()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q3->id, 'option_text' => 'True', 'is_correct' => false]);
        $correct3 = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q3->id, 'option_text' => 'False']);

        return compact('quiz', 'q1', 'correct1', 'q2', 'correct2a', 'correct2b', 'q3', 'correct3');
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_attempts_for_own_employee(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        QuizAttempt::factory()->count(2)->create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/quiz-attempts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_scopes_to_own_attempts_without_manage_permission(): void
    {
        $user1     = $this->userWithRole('Employee');
        $employee1 = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user1->id]);
        $employee2 = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        QuizAttempt::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee1->id]);
        QuizAttempt::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee2->id]);

        $this->actingAs($user1, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/quiz-attempts')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/quiz-attempts')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_starts_attempt(): void
    {
        $quiz     = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $quiz->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.attempt_number', 1);
    }

    public function test_store_increments_attempt_number(): void
    {
        $quiz     = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        QuizAttempt::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $quiz->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attempt_number', 2);
    }

    public function test_store_enforces_max_attempts(): void
    {
        $quiz     = Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'max_attempts' => 2]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        QuizAttempt::factory()->count(2)->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $quiz->id,
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_creating_attempt_for_other_employee_without_permission(): void
    {
        $quiz      = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee  = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $user      = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $quiz->id,
                'employee_id' => $employee->id,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_quiz_from_other_tenant(): void
    {
        $otherQuiz = Quiz::factory()->create();
        $employee  = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quiz-attempts', [
                'quiz_id'     => $otherQuiz->id,
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_attempt(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quiz-attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $attempt->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $attempt = QuizAttempt::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quiz-attempts/{$attempt->id}")
            ->assertForbidden();
    }

    public function test_show_forbidden_for_other_employee(): void
    {
        $otherEmployee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $attempt       = QuizAttempt::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $otherEmployee->id,
        ]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quiz-attempts/{$attempt->id}")
            ->assertForbidden();
    }

    // ── Submit — grading ──────────────────────────────────────────────────────

    public function test_submit_grades_all_correct_answers_as_passed(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'correct1' => $c1, 'q2' => $q2, 'correct2a' => $c2a, 'correct2b' => $c2b, 'q3' => $q3, 'correct3' => $c3] = $this->makeQuizWithQuestions();
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'selected_option_ids' => [$c1->id]],
                    ['question_id' => $q2->id, 'selected_option_ids' => [$c2a->id, $c2b->id]],
                    ['question_id' => $q3->id, 'selected_option_ids' => [$c3->id]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.score_percentage', '100.00');
    }

    public function test_submit_grades_no_answers_as_failed(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q2' => $q2, 'q3' => $q3] = $this->makeQuizWithQuestions();
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'selected_option_ids' => []],
                    ['question_id' => $q2->id, 'selected_option_ids' => []],
                    ['question_id' => $q3->id, 'selected_option_ids' => []],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', false)
            ->assertJsonPath('data.score_percentage', '0.00');
    }

    public function test_submit_partial_score_uses_pass_threshold(): void
    {
        // quiz pass_threshold=60; 3 questions of 1 point each
        // answer 2 correctly → 66.67% → passed
        ['quiz' => $quiz, 'q1' => $q1, 'correct1' => $c1, 'q2' => $q2, 'correct2a' => $c2a, 'correct2b' => $c2b, 'q3' => $q3] = $this->makeQuizWithQuestions();
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'selected_option_ids' => [$c1->id]],
                    ['question_id' => $q2->id, 'selected_option_ids' => [$c2a->id, $c2b->id]],
                    ['question_id' => $q3->id, 'selected_option_ids' => []],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', true);
    }

    public function test_submit_multiple_choice_wrong_if_extra_option_selected(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'correct1' => $c1, 'q2' => $q2, 'correct2a' => $c2a, 'correct2b' => $c2b, 'q3' => $q3, 'correct3' => $c3] = $this->makeQuizWithQuestions();

        $wrongOption = QuestionOption::factory()->create(['tenant_id' => $this->tenant->id, 'question_id' => $q2->id, 'is_correct' => false]);

        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $q1->id, 'selected_option_ids' => [$c1->id]],
                    // select correct options + one wrong extra
                    ['question_id' => $q2->id, 'selected_option_ids' => [$c2a->id, $c2b->id, $wrongOption->id]],
                    ['question_id' => $q3->id, 'selected_option_ids' => [$c3->id]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.score_percentage', '66.67');
    }

    public function test_submit_rejects_already_submitted_attempt(): void
    {
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->submitted()->create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", ['answers' => []])
            ->assertUnprocessable();
    }

    public function test_submit_rejects_invalid_question_not_in_quiz(): void
    {
        $quiz     = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->adminUser->id]);
        $attempt  = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'quiz_id'     => $quiz->id,
            'employee_id' => $employee->id,
        ]);
        $otherQuestion = Question::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", [
                'answers' => [
                    ['question_id' => $otherQuestion->id, 'selected_option_ids' => []],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_submit_forbidden_for_other_employees_attempt(): void
    {
        $otherEmployee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $attempt       = QuizAttempt::factory()->inProgress()->create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $otherEmployee->id,
        ]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/quiz-attempts/{$attempt->id}/submit", ['answers' => []])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_attempt(): void
    {
        $attempt = QuizAttempt::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/quiz-attempts/{$attempt->id}")
            ->assertOk();

        $this->assertDatabaseMissing('quiz_attempts', ['id' => $attempt->id]);
    }
}
