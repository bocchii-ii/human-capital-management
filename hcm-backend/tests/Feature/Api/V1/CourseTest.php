<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class CourseTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_courses(): void
    {
        Course::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/courses?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_category(): void
    {
        Course::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'compliance']);
        Course::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'technical']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/courses?category=compliance')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_searches_by_title(): void
    {
        Course::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Fire Safety Training']);
        Course::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Leadership 101']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/courses?search=Safety')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/courses')
            ->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_draft_course(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/courses', [
                'title'    => 'GDPR Compliance Basics',
                'category' => 'compliance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'GDPR Compliance Basics');
    }

    public function test_store_requires_title_and_category(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/courses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'category']);
    }

    public function test_store_rejects_invalid_category(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/courses', ['title' => 'Test', 'category' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_store_denied_without_permission(): void
    {
        $user = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/courses', ['title' => 'Test', 'category' => 'technical'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_course(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $course->id);
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $course = Course::factory()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/courses/{$course->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_course(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/courses/{$course->id}", ['title' => 'Updated Title'])
            ->assertOk();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Updated Title']);
    }

    public function test_update_denied_without_permission(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/courses/{$course->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_course(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertOk();

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function test_publish_transitions_draft_course_to_published(): void
    {
        $course = Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_publish_sets_published_at_timestamp(): void
    {
        $course = Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertOk();

        $this->assertNotNull($course->fresh()->published_at);
    }

    public function test_publish_rejects_non_draft_course(): void
    {
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertUnprocessable();
    }

    public function test_publish_denied_without_permission(): void
    {
        $course = Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
        $user   = $this->userWithRole('Employee');

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertForbidden();
    }

    // ── Archive ───────────────────────────────────────────────────────────────

    public function test_archive_transitions_published_course_to_archived(): void
    {
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_archive_rejects_non_published_course(): void
    {
        $course = Course::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/archive")
            ->assertUnprocessable();
    }

    public function test_archive_denied_for_other_tenant(): void
    {
        $course = Course::factory()->published()->create(); // different tenant

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/courses/{$course->id}/archive")
            ->assertForbidden();
    }
}
