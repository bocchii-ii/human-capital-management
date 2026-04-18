<?php

namespace Tests\Unit\Models;

use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonTest extends TestCase
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
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Lesson::class));
    }

    public function test_fillable_attributes(): void
    {
        $fillable = (new Lesson)->getFillable();

        foreach (['tenant_id', 'course_module_id', 'title', 'content_type', 'content', 'video_url', 'file_url', 'duration_minutes', 'sort_order', 'is_required'] as $attr) {
            $this->assertContains($attr, $fillable);
        }
    }

    public function test_is_required_cast_to_boolean(): void
    {
        $lesson = Lesson::factory()->make(['is_required' => 1]);

        $this->assertIsBool($lesson->is_required);
    }

    public function test_duration_minutes_cast_to_integer(): void
    {
        $lesson = Lesson::factory()->video()->make(['duration_minutes' => '30']);

        $this->assertIsInt($lesson->duration_minutes);
    }

    public function test_belongs_to_tenant(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $lesson->tenant->id);
    }

    public function test_belongs_to_module(): void
    {
        $module = CourseModule::factory()->create(['tenant_id' => $this->tenant->id]);
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id, 'course_module_id' => $module->id]);

        $this->assertEquals($module->id, $lesson->module->id);
    }

    public function test_video_factory_state(): void
    {
        $lesson = Lesson::factory()->video()->make();

        $this->assertEquals('video', $lesson->content_type);
        $this->assertNotNull($lesson->video_url);
    }

    public function test_pdf_factory_state(): void
    {
        $lesson = Lesson::factory()->pdf()->make();

        $this->assertEquals('pdf', $lesson->content_type);
        $this->assertNotNull($lesson->file_url);
    }

    public function test_quiz_factory_state(): void
    {
        $lesson = Lesson::factory()->quiz()->make();

        $this->assertEquals('quiz', $lesson->content_type);
    }

    public function test_optional_factory_state(): void
    {
        $lesson = Lesson::factory()->optional()->make();

        $this->assertFalse($lesson->is_required);
    }

    public function test_soft_delete_does_not_permanently_remove_record(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        $id     = $lesson->id;

        $lesson->delete();

        $this->assertNull(Lesson::find($id));
        $this->assertNotNull(Lesson::withTrashed()->find($id));
    }

    public function test_can_be_restored(): void
    {
        $lesson = Lesson::factory()->create(['tenant_id' => $this->tenant->id]);
        $lesson->delete();
        $lesson->restore();

        $this->assertNotNull(Lesson::find($lesson->id));
    }
}
