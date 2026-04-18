<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class CourseModuleTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_modules(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        CourseModule::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/course-modules?course_id={$course->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_course_id(): void
    {
        $course1 = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $course2 = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course1->id]);
        CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course2->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/course-modules?course_id={$course1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/course-modules')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_module(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/course-modules', [
                'course_id' => $course->id,
                'title'     => 'Module 1: Introduction',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Module 1: Introduction');
    }

    public function test_store_requires_course_id_and_title(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/course-modules', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['course_id', 'title']);
    }

    public function test_store_rejects_course_from_other_tenant(): void
    {
        $otherCourse = Course::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/course-modules', [
                'course_id' => $otherCourse->id,
                'title'     => 'Module 1',
            ])
            ->assertUnprocessable();
    }

    public function test_store_denied_without_permission(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/course-modules', ['course_id' => $course->id, 'title' => 'Module 1'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_module_with_lessons(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/course-modules/{$module->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $module->id)
            ->assertJsonCount(1, 'data.lessons');
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $module = CourseModule::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/course-modules/{$module->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_module(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/course-modules/{$module->id}", ['title' => 'Updated Module'])
            ->assertOk();

        $this->assertDatabaseHas('course_modules', ['id' => $module->id, 'title' => 'Updated Module']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_module(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/course-modules/{$module->id}")
            ->assertOk();

        $this->assertSoftDeleted('course_modules', ['id' => $module->id]);
    }
}
