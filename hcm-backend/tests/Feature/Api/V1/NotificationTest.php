<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class NotificationTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        Storage::fake('local');
    }

    private function makeNotification(User $user, array $overrides = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $user->id,
            'type'      => 'enrollment.completed',
            'title'     => 'You completed a course',
            'body'      => 'Congratulations!',
        ], $overrides));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_own_notifications_with_unread_count(): void
    {
        $this->makeNotification($this->adminUser);
        $this->makeNotification($this->adminUser, ['read_at' => now()]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_index_unread_only_filter(): void
    {
        $this->makeNotification($this->adminUser);
        $this->makeNotification($this->adminUser, ['read_at' => now()]);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/notifications?unread_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_user_only_sees_own_notifications(): void
    {
        $other = $this->userWithRole('Employee');
        $this->makeNotification($other);
        $this->makeNotification($this->adminUser);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_marks_notification_as_read(): void
    {
        $n = $this->makeNotification($this->adminUser);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/notifications/{$n->id}")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $other = $this->userWithRole('Employee');
        $n     = $this->makeNotification($other);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->getJson("/api/v1/notifications/{$n->id}")
            ->assertForbidden();
    }

    // ── Mark Read ─────────────────────────────────────────────────────────────

    public function test_mark_read_sets_read_at(): void
    {
        $n = $this->makeNotification($this->adminUser);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    // ── Mark All Read ─────────────────────────────────────────────────────────

    public function test_mark_all_read_clears_unread_count(): void
    {
        $this->makeNotification($this->adminUser);
        $this->makeNotification($this->adminUser);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $unread = AppNotification::where('user_id', $this->adminUser->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unread);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_own_notification(): void
    {
        $n = $this->makeNotification($this->adminUser);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/notifications/{$n->id}")
            ->assertOk();

        $this->assertDatabaseMissing('app_notifications', ['id' => $n->id]);
    }

    public function test_destroy_forbidden_for_other_user(): void
    {
        $other = $this->userWithRole('Employee');
        $n     = $this->makeNotification($other);

        $this->actingAsAdmin()
            ->withHeaders($this->withTenantHeader())
            ->deleteJson("/api/v1/notifications/{$n->id}")
            ->assertForbidden();
    }

    // ── Integration: auto-notify on enrollment completion ────────────────────

    public function test_notification_created_on_enrollment_completed(): void
    {
        $user     = $this->userWithRole('Employee');
        $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id, 'tenant_id' => $this->tenant->id]);
        $lesson = Lesson::factory()->create([
            'course_module_id' => $module->id,
            'tenant_id'        => $this->tenant->id,
            'content_type'     => 'text',
            'is_required'      => true,
        ]);

        $enrollment = Enrollment::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'course_id'   => $course->id,
            'status'      => 'in_progress',
            'enrolled_at' => now(),
            'started_at'  => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withTenantHeader())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type'    => 'enrollment.completed',
        ]);
    }
}
