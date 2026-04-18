<?php

namespace Tests\Unit\Models;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(QuestionOption::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new QuestionOption)->getFillable();

        foreach (['tenant_id', 'question_id', 'option_text', 'is_correct', 'sort_order'] as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_is_correct_cast_to_boolean(): void
    {
        $option = QuestionOption::factory()->create(['is_correct' => 1]);
        $this->assertIsBool($option->is_correct);
    }

    public function test_belongs_to_tenant(): void
    {
        $option = QuestionOption::factory()->create();
        $this->assertInstanceOf(Tenant::class, $option->tenant);
    }

    public function test_belongs_to_question(): void
    {
        $option = QuestionOption::factory()->create();
        $this->assertInstanceOf(Question::class, $option->question);
    }

    public function test_factory_correct_state(): void
    {
        $option = QuestionOption::factory()->correct()->create();
        $this->assertTrue($option->is_correct);
    }

    public function test_soft_delete_and_restore(): void
    {
        $option = QuestionOption::factory()->create();
        $option->delete();

        $this->assertSoftDeleted('question_options', ['id' => $option->id]);

        $option->restore();
        $this->assertDatabaseHas('question_options', ['id' => $option->id, 'deleted_at' => null]);
    }
}
