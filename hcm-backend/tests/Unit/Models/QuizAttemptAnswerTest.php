<?php

namespace Tests\Unit\Models;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_use_soft_deletes(): void
    {
        $this->assertNotContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(QuizAttemptAnswer::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new QuizAttemptAnswer)->getFillable();

        foreach (['quiz_attempt_id', 'question_id', 'selected_option_ids', 'is_correct'] as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_selected_option_ids_cast_to_array(): void
    {
        $answer = QuizAttemptAnswer::factory()->create(['selected_option_ids' => [1, 2, 3]]);
        $this->assertIsArray($answer->selected_option_ids);
    }

    public function test_is_correct_cast_to_boolean(): void
    {
        $answer = QuizAttemptAnswer::factory()->create(['is_correct' => 1]);
        $this->assertIsBool($answer->is_correct);
    }

    public function test_belongs_to_attempt(): void
    {
        $answer = QuizAttemptAnswer::factory()->create();
        $this->assertInstanceOf(QuizAttempt::class, $answer->attempt);
    }

    public function test_belongs_to_question(): void
    {
        $answer = QuizAttemptAnswer::factory()->create();
        $this->assertInstanceOf(Question::class, $answer->question);
    }
}
