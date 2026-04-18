<?php

namespace Tests\Unit\Models;

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

    public function test_fillable_fields(): void
    {
        $item = new LearningPathCourse();
        $this->assertEqualsCanonicalizing(
            ['learning_path_id', 'course_id', 'sort_order', 'is_required'],
            $item->getFillable()
        );
    }

    public function test_is_required_cast_to_boolean(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item = LearningPathCourse::factory()->create([
            'learning_path_id' => $path->id,
            'course_id'        => $course->id,
            'is_required'      => 1,
        ]);
        $this->assertIsBool($item->is_required);
    }

    public function test_optional_state(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item = LearningPathCourse::factory()->optional()->create([
            'learning_path_id' => $path->id,
            'course_id'        => $course->id,
        ]);
        $this->assertFalse($item->is_required);
    }

    public function test_belongs_to_learning_path(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item = LearningPathCourse::factory()->create([
            'learning_path_id' => $path->id,
            'course_id'        => $course->id,
        ]);
        $this->assertInstanceOf(LearningPath::class, $item->learningPath);
    }

    public function test_belongs_to_course(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $course = Course::factory()->published()->create(['tenant_id' => $this->tenant->id]);
        $item = LearningPathCourse::factory()->create([
            'learning_path_id' => $path->id,
            'course_id'        => $course->id,
        ]);
        $this->assertInstanceOf(Course::class, $item->course);
    }
}
