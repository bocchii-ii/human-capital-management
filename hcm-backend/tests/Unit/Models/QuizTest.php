<?php

namespace Tests\Unit\Models;

use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(Quiz::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Quiz)->getFillable();

        foreach (['tenant_id', 'lesson_id', 'title', 'description', 'pass_threshold', 'max_attempts', 'time_limit_minutes', 'shuffle_questions'] as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_shuffle_questions_cast_to_boolean(): void
    {
        $quiz = Quiz::factory()->create(['shuffle_questions' => 1]);
        $this->assertIsBool($quiz->shuffle_questions);
    }

    public function test_pass_threshold_cast_to_integer(): void
    {
        $quiz = Quiz::factory()->create(['pass_threshold' => 80]);
        $this->assertIsInt($quiz->pass_threshold);
    }

    public function test_belongs_to_tenant(): void
    {
        $quiz = Quiz::factory()->create();
        $this->assertInstanceOf(Tenant::class, $quiz->tenant);
    }

    public function test_belongs_to_lesson(): void
    {
        $quiz = Quiz::factory()->create();
        $this->assertInstanceOf(Lesson::class, $quiz->lesson);
    }

    public function test_has_many_questions(): void
    {
        $quiz = Quiz::factory()->create();
        Question::factory()->count(2)->create(['quiz_id' => $quiz->id, 'tenant_id' => $quiz->tenant_id]);

        $this->assertCount(2, $quiz->questions);
    }

    public function test_has_many_attempts(): void
    {
        $quiz = Quiz::factory()->create();
        QuizAttempt::factory()->count(2)->create(['quiz_id' => $quiz->id, 'tenant_id' => $quiz->tenant_id]);

        $this->assertCount(2, $quiz->attempts);
    }

    public function test_soft_delete_and_restore(): void
    {
        $quiz = Quiz::factory()->create();
        $quiz->delete();

        $this->assertSoftDeleted('quizzes', ['id' => $quiz->id]);

        $quiz->restore();
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'deleted_at' => null]);
    }
}
