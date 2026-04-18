<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseModuleTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(CourseModule::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new CourseModule)->getFillable();

        foreach (['tenant_id', 'course_id', 'title', 'description', 'sort_order'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_sort_order_cast_to_integer(): void
    {
        $module = CourseModule::factory()->make(['sort_order' => '3']);

        $this->assertIsInt($module->sort_order);
    }

    public function test_belongs_to_tenant(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $module->tenant->id);
    }

    public function test_belongs_to_course(): void
    {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);

        $this->assertEquals($course->id, $module->course->id);
    }

    public function test_has_many_lessons(): void
    {
        $module  = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $lesson1 = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);
        $lesson2 = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);

        $this->assertCount(2, $module->lessons);
        $this->assertTrue($module->lessons->contains($lesson1));
        $this->assertTrue($module->lessons->contains($lesson2));
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $id     = $module->id;

        $module->delete();

        $this->assertNull(CourseModule::find($id));
        $this->assertNotNull(CourseModule::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $module->delete();
        $module->restore();

        $this->assertNotNull(CourseModule::find($module->id));
    }
}
