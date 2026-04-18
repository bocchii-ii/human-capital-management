<?php

namespace Tests\Feature\Api\V1;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class QuestionOptionTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_options(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);
        QuestionOption::factory()->count(3)->create([
            'tenant_id'   => $this->tenant->id,
            'question_id' => $question->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/question-options?question_id={$question->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/question-options')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_option(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/question-options', [
                'question_id' => $question->id,
                'option_text' => 'Paris',
                'is_correct'  => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.option_text', 'Paris');
    }

    public function test_store_requires_question_id_and_option_text(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/question-options', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['question_id', 'option_text']);
    }

    public function test_store_rejects_question_from_other_tenant(): void
    {
        $otherQuestion = Question::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/question-options', [
                'question_id' => $otherQuestion->id,
                'option_text' => 'Option A',
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $question = Question::factory()->create(['tenant_id' => $this->tenant->id]);
        $user     = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/question-options', [
                'question_id' => $question->id,
                'option_text' => 'Option A',
            ])
            ->assertForbidden();
    }

    // ── is_correct visibility ─────────────────────────────────────────────────

    public function test_is_correct_visible_to_admin(): void
    {
        $option = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/question-options/{$option->id}")
            ->assertOk();

        $this->assertArrayHasKey('is_correct', $response->json('data'));
    }

    public function test_is_correct_hidden_from_employee(): void
    {
        $option = QuestionOption::factory()->correct()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/question-options/{$option->id}")
            ->assertOk();

        $this->assertArrayNotHasKey('is_correct', $response->json('data'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_forbidden_for_other_tenant(): void
    {
        $option = QuestionOption::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/question-options/{$option->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_option(): void
    {
        $option = QuestionOption::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/question-options/{$option->id}", ['option_text' => 'Updated text'])
            ->assertOk();

        $this->assertDatabaseHas('question_options', ['id' => $option->id, 'option_text' => 'Updated text']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_option(): void
    {
        $option = QuestionOption::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/question-options/{$option->id}")
            ->assertOk();

        $this->assertSoftDeleted('question_options', ['id' => $option->id]);
    }
}
