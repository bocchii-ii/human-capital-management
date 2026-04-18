<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class LearningPathCourseTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_items(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course1 = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $course2 = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course1->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course2->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/learning-path-courses?learning_path_id={$path->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/learning-path-courses')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_item(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-path-courses', [
                'learning_path_id' => $path->id,
                'course_id'        => $course->id,
                'sort_order'       => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.learning_path_id', $path->id);
    }

    public function test_store_rejects_learning_path_from_other_tenant(): void
    {
        $otherPath = LearningPath::factory()->create();
        $course    = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-path-courses', [
                'learning_path_id' => $otherPath->id,
                'course_id'        => $course->id,
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-path-courses', [
                'learning_path_id' => $path->id,
                'course_id'        => $course->id,
            ])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_forbidden_for_other_tenant(): void
    {
        $item = LearningPathCourse::factory()->create([
            'learning_path_id' => LearningPath::factory()->create()->id,
            'course_id'        => Course::factory()->create()->id,
        ]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/learning-path-courses/{$item->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_sort_order(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item   = LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/learning-path-courses/{$item->id}", ['sort_order' => 5])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 5);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_item(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item   = LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/learning-path-courses/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('learning_path_courses', ['id' => $item->id]);
    }
}
