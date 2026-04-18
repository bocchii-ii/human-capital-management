<?php

namespace Tests\Unit\Models;

use App\Models\Employee;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_use_soft_deletes(): void
    {
        $this->assertNotContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(QuizAttempt::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new QuizAttempt)->getFillable();

        foreach (['tenant_id', 'quiz_id', 'employee_id', 'attempt_number', 'status', 'score_percentage', 'passed', 'started_at', 'submitted_at'] as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_passed_cast_to_boolean(): void
    {
        $attempt = QuizAttempt::factory()->passed()->create();
        $this->assertIsBool($attempt->passed);
    }

    public function test_started_at_cast_to_datetime(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $attempt->started_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $this->assertInstanceOf(Tenant::class, $attempt->tenant);
    }

    public function test_belongs_to_quiz(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $this->assertInstanceOf(Quiz::class, $attempt->quiz);
    }

    public function test_belongs_to_employee(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $this->assertInstanceOf(Employee::class, $attempt->employee);
    }

    public function test_has_many_answers(): void
    {
        $attempt = QuizAttempt::factory()->create();
        QuizAttemptAnswer::factory()->count(2)->create(['quiz_attempt_id' => $attempt->id]);

        $this->assertCount(2, $attempt->answers);
    }

    public function test_factory_in_progress_state(): void
    {
        $attempt = QuizAttempt::factory()->inProgress()->create();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNull($attempt->score_percentage);
    }

    public function test_factory_submitted_state(): void
    {
        $attempt = QuizAttempt::factory()->submitted()->create();
        $this->assertSame('submitted', $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_factory_passed_state(): void
    {
        $attempt = QuizAttempt::factory()->passed()->create();
        $this->assertTrue($attempt->passed);
    }

    public function test_factory_failed_state(): void
    {
        $attempt = QuizAttempt::factory()->failed()->create();
        $this->assertFalse($attempt->passed);
    }
}
