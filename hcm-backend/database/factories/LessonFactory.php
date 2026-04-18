<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'course_module_id' => CourseModule::factory(),
            'title'            => fake()->sentence(4),
            'content_type'     => 'text',
            'content'          => fake()->paragraphs(3, true),
            'video_url'        => null,
            'file_url'         => null,
            'duration_minutes' => null,
            'sort_order'       => 0,
            'is_required'      => true,
        ];
    }

    public function video(): static
    {
        return $this->state([
            'content_type'     => 'video',
            'content'          => null,
            'video_url'        => 'https://example.com/video.mp4',
            'duration_minutes' => fake()->numberBetween(5, 60),
        ]);
    }

    public function pdf(): static
    {
        return $this->state([
            'content_type' => 'pdf',
            'content'      => null,
            'file_url'     => 'https://example.com/document.pdf',
        ]);
    }

    public function text(): static
    {
        return $this->state([
            'content_type' => 'text',
            'content'      => fake()->paragraphs(3, true),
        ]);
    }

    public function quiz(): static
    {
        return $this->state([
            'content_type' => 'quiz',
            'content'      => null,
        ]);
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }
}
