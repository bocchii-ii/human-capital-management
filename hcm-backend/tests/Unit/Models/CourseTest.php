<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Course::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Course)->getFillable();

        foreach (['tenant_id', 'created_by', 'title', 'slug', 'description', 'category', 'status', 'is_active', 'published_at'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $course = Course::factory()->make(['is_active' => 1]);

        $this->assertIsBool($course->is_active);
    }

    public function test_published_at_cast_to_datetime(): void
    {
        $course = Course::factory()->published()->make(['tenant_id' => $this->tenant->id]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $course->published_at);
    }

    public function test_belongs_to_tenant(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $course->tenant->id);
    }

    public function test_belongs_to_creator(): void
    {
        $user   = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $user->id]);

        $this->assertEquals($user->id, $course->creator->id);
    }

    public function test_has_many_modules(): void
    {
        $course  = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $module1 = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $module2 = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);

        $this->assertCount(2, $course->modules);
        $this->assertTrue($course->modules->contains($module1));
        $this->assertTrue($course->modules->contains($module2));
    }

    public function test_has_many_through_lessons(): void
    {
        $course  = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $module  = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);
        $lesson  = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);

        $this->assertCount(1, $course->lessons);
        $this->assertTrue($course->lessons->contains($lesson));
    }

    public function test_draft_factory_state(): void
    {
        $course = Course::factory()->draft()->make();

        $this->assertEquals('draft', $course->status);
        $this->assertNull($course->published_at);
    }

    public function test_published_factory_state(): void
    {
        $course = Course::factory()->published()->make();

        $this->assertEquals('published', $course->status);
        $this->assertNotNull($course->published_at);
    }

    public function test_archived_factory_state(): void
    {
        $course = Course::factory()->archived()->make();

        $this->assertEquals('archived', $course->status);
        $this->assertNotNull($course->published_at);
    }

    public function test_inactive_factory_state(): void
    {
        $course = Course::factory()->inactive()->make();

        $this->assertFalse($course->is_active);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $id     = $course->id;

        $course->delete();

        $this->assertNull(Course::find($id));
        $this->assertNotNull(Course::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $course->delete();
        $course->restore();

        $this->assertNotNull(Course::find($course->id));
    }
}
