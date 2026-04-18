<?php

namespace Tests\Feature\Api\V1;

use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class LessonTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_lessons(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        Lesson::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/lessons?course_module_id={$module->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_course_module_id(): void
    {
        $module1 = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $module2 = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module1->id]);
        Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module2->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/lessons?course_module_id={$module1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/lessons')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_text_lesson(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/lessons', [
                'course_module_id' => $module->id,
                'title'            => 'Introduction to GDPR',
                'content_type'     => 'text',
                'content'          => '<p>Welcome to this lesson.</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.content_type', 'text')
            ->assertJsonPath('data.title', 'Introduction to GDPR');
    }

    public function test_store_creates_video_lesson(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/lessons', [
                'course_module_id' => $module->id,
                'title'            => 'Video Overview',
                'content_type'     => 'video',
                'video_url'        => 'https://example.com/video.mp4',
                'duration_minutes' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('data.content_type', 'video');
    }

    public function test_store_requires_module_id_title_and_content_type(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/lessons', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['course_module_id', 'title', 'content_type']);
    }

    public function test_store_rejects_module_from_other_tenant(): void
    {
        $otherModule = CourseModule::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/lessons', [
                'course_module_id' => $otherModule->id,
                'title'            => 'Lesson 1',
                'content_type'     => 'text',
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/lessons', [
                'course_module_id' => $module->id,
                'title'            => 'Lesson 1',
                'content_type'     => 'text',
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_lesson(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/lessons/{$lesson->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $lesson->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $lesson = Lesson::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/lessons/{$lesson->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_lesson(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/lessons/{$lesson->id}", ['title' => 'Updated Lesson Title'])
            ->assertOk();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Updated Lesson Title']);
    }

    public function test_update_denied_without_permission(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/lessons/{$lesson->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_lesson(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/lessons/{$lesson->id}")
            ->assertOk();

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
    }
}
