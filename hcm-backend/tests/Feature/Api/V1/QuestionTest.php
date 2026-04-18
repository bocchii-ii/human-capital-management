<?php

namespace Tests\Feature\Api\V1;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class QuestionTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_questions(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        Question::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/questions?quiz_id={$quiz->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_quiz_id(): void
    {
        $quiz1 = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $quiz2 = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        Question::factory()->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz1->id]);
        Question::factory()->create(['tenant_id' => $this->tenant->id, 'quiz_id' => $quiz2->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/questions?quiz_id={$quiz1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/questions')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_question(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/questions', [
                'quiz_id'       => $quiz->id,
                'question_text' => 'What is 2 + 2?',
                'question_type' => 'single_choice',
            ])
            ->assertCreated()
            ->assertJsonPath('data.question_type', 'single_choice');
    }

    public function test_store_requires_quiz_id_question_text_and_type(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/questions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quiz_id', 'question_text', 'question_type']);
    }

    public function test_store_rejects_invalid_question_type(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/questions', [
                'quiz_id'       => $quiz->id,
                'question_text' => 'Test?',
                'question_type' => 'essay',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question_type']);
    }

    public function test_store_rejects_quiz_from_other_tenant(): void
    {
        $otherQuiz = Quiz::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/questions', [
                'quiz_id'       => $otherQuiz->id,
                'question_text' => 'Test?',
                'question_type' => 'single_choice',
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/questions', [
                'quiz_id'       => $quiz->id,
                'question_text' => 'Test?',
                'question_type' => 'single_choice',
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_question_with_options(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/questions/{$question->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $question->id)
            ->assertJsonStructure(['data' => ['options']]);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $question = Question::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/questions/{$question->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_question(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/questions/{$question->id}", ['question_text' => 'Updated question?'])
            ->assertOk();

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'question_text' => 'Updated question?']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_question(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/questions/{$question->id}")
            ->assertOk();

        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }
}
