<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LearningPathCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class LearningPathTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_learning_paths(): void
    {
        LearningPath::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/learning-paths')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_is_active(): void
    {
        LearningPath::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        LearningPath::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/learning-paths?is_active=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_search(): void
    {
        LearningPath::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Engineer Onboarding']);
        LearningPath::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Sales Training']);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/learning-paths?search=Engineer')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/learning-paths')->assertUnauthorized();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_learning_path(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-paths', ['title' => 'New Hire Path'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New Hire Path');
    }

    public function test_store_requires_title(): void
    {
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-paths', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_denied_without_permission(): void
    {
        $employee = $this->userWithRole('Employee');

        $this->actingAs($employee, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/learning-paths', ['title' => 'Path'])
            ->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_path_with_courses(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/learning-paths/{$path->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $path->id)
            ->assertJsonCount(1, 'data.path_courses');
    }

    public function test_show_forbidden_for_other_tenant(): void
    {
        $path = LearningPath::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/learning-paths/{$path->id}")
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_learning_path(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->putJson("/api/v1/learning-paths/{$path->id}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_path(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/learning-paths/{$path->id}")
            ->assertOk();

        $this->assertSoftDeleted('learning_paths', ['id' => $path->id]);
    }

    // ── Assign ────────────────────────────────────────────────────────────────

    public function test_assign_creates_enrollments_for_all_courses(): void
    {
        $path    = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course1 = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $course2 = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course1->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course2->id]);

        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $employee->id])
            ->assertOk();

        $this->assertEquals(2, $response->json('created'));
        $this->assertEquals(0, $response->json('skipped'));
        $this->assertCount(2, $response->json('enrollments'));

        $this->assertDatabaseHas('enrollments', ['employee_id' => $employee->id, 'course_id' => $course1->id]);
        $this->assertDatabaseHas('enrollments', ['employee_id' => $employee->id, 'course_id' => $course2->id]);
    }

    public function test_assign_is_idempotent_skips_existing_enrollments(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course->id]);

        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        // First assign
        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $employee->id]);

        // Second assign — should skip
        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $employee->id])
            ->assertOk();

        $this->assertEquals(0, $response->json('created'));
        $this->assertEquals(1, $response->json('skipped'));
        $this->assertCount(1, Enrollment::where('employee_id', $employee->id)->get());
    }

    public function test_assign_reactivates_withdrawn_enrollment(): void
    {
        $path   = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->create(['learning_path_id' => $path->id, 'course_id' => $course->id]);

        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create withdrawn enrollment
        Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'withdrawn',
            'enrolled_at' => now()->subDays(5),
        ]);

        $response = $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $employee->id])
            ->assertOk();

        $this->assertEquals(1, $response->json('created'));
        $this->assertDatabaseHas('enrollments', [
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'enrolled',
        ]);
    }

    public function test_assign_denied_without_enrollment_manage_permission(): void
    {
        $path     = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $trainer  = $this->userWithRole('Employee');

        $this->actingAs($trainer, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $employee->id])
            ->assertForbidden();
    }

    public function test_assign_rejects_employee_from_other_tenant(): void
    {
        $path     = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherEmp = Employee::factory()->create();

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/learning-paths/{$path->id}/assign", ['employee_id' => $otherEmp->id])
            ->assertUnprocessable();
    }
}
