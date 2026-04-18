<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\LearningPath;
use App\Models\LearningPathCourse;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public function test_fillable_fields(): void
    {
        $path = new LearningPath();
        $this->assertEqualsCanonicalizing(
            ['tenant_id', 'title', 'description', 'target_role', 'target_department_id', 'is_active'],
            $path->getFillable()
        );
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(LearningPath::class));
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => 1]);
        $this->assertIsBool($path->is_active);
    }

    public function test_inactive_state(): void
    {
        $path = LearningPath::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
        $this->assertFalse($path->is_active);
    }

    public function test_belongs_to_tenant(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(Tenant::class, $path->tenant);
    }

    public function test_has_many_path_courses(): void
    {
        $path = LearningPath::factory()->create(['tenant_id' => $this->tenant->id]);
        LearningPathCourse::factory()->count(2)->create(['learning_path_id' => $path->id]);

        $this->assertCount(2, $path->pathCourses);
    }
}
