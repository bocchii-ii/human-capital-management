<?php

namespace Tests\Unit\Models;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(Question::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Question)->getFillable();

        foreach (['tenant_id', 'quiz_id', 'question_text', 'question_type', 'points', 'sort_order', 'explanation'] as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_points_cast_to_integer(): void
    {
        $question = Question::factory()->create(['points' => '2']);
        $this->assertIsInt($question->points);
    }

    public function test_belongs_to_tenant(): void
    {
        $question = Question::factory()->create();
        $this->assertInstanceOf(Tenant::class, $question->tenant);
    }

    public function test_belongs_to_quiz(): void
    {
        $question = Question::factory()->create();
        $this->assertInstanceOf(Quiz::class, $question->quiz);
    }

    public function test_has_many_options(): void
    {
        $question = Question::factory()->create();
        QuestionOption::factory()->count(3)->create([
            'question_id' => $question->id,
            'tenant_id'   => $question->tenant_id,
        ]);

        $this->assertCount(3, $question->options);
    }

    public function test_factory_single_choice_state(): void
    {
        $question = Question::factory()->singleChoice()->create();
        $this->assertSame('single_choice', $question->question_type);
    }

    public function test_factory_multiple_choice_state(): void
    {
        $question = Question::factory()->multipleChoice()->create();
        $this->assertSame('multiple_choice', $question->question_type);
    }

    public function test_factory_true_false_state(): void
    {
        $question = Question::factory()->trueFalse()->create();
        $this->assertSame('true_false', $question->question_type);
    }

    public function test_soft_delete_and_restore(): void
    {
        $question = Question::factory()->create();
        $question->delete();

        $this->assertSoftDeleted('questions', ['id' => $question->id]);

        $question->restore();
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'deleted_at' => null]);
    }
}
