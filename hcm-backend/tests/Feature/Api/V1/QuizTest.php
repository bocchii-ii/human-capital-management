<?php

namespace Tests\Feature\Api\V1;

use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class QuizTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_quizzes(): void
    {
        Quiz::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/quizzes')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_lesson_id(): void
    {
        $lesson1 = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        $lesson2 = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'lesson_id' => $lesson1->id]);
        Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'lesson_id' => $lesson2->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quizzes?lesson_id={$lesson1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/quizzes')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_quiz(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quizzes', [
                'lesson_id'      => $lesson->id,
                'pass_threshold' => 75,
            ])
            ->assertCreated()
            ->assertJsonPath('data.pass_threshold', 75);
    }

    public function test_store_requires_lesson_id(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quizzes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lesson_id']);
    }

    public function test_store_rejects_lesson_from_other_tenant(): void
    {
        $otherLesson = Lesson::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quizzes', ['lesson_id' => $otherLesson->id])
            ->assertUnprocessable();
    }

    public function test_store_rejects_duplicate_quiz_for_same_lesson(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        Quiz::factory()->create(['tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quizzes', ['lesson_id' => $lesson->id])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/quizzes', ['lesson_id' => $lesson->id])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_quiz_with_questions(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $quiz->id)
            ->assertJsonStructure(['data' => ['questions']]);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $quiz = Quiz::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/quizzes/{$quiz->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_quiz(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/quizzes/{$quiz->id}", ['pass_threshold' => 90])
            ->assertOk();

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'pass_threshold' => 90]);
    }

    public function test_update_denied_without_permission(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/quizzes/{$quiz->id}", ['pass_threshold' => 90])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_quiz(): void
    {
        $quiz = Quiz::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/quizzes/{$quiz->id}")
            ->assertOk();

        $this->assertSoftDeleted('quizzes', ['id' => $quiz->id]);
    }
}
