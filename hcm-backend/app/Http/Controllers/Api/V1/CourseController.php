<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $courses = Course::where('tenant_id', $tenant->id)
            ->with(['creator'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 15));

        return CourseResource::collection($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category'    => ['required', 'in:compliance,technical,soft_skills'],
            'is_active'   => ['boolean'],
        ]);

        $course = Course::create(array_merge($data, [
            'tenant_id'  => $tenant->id,
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]));

        return (new CourseResource($course->load('creator')))->response()->setStatusCode(201);
    }

    public function show(Course $course): CourseResource
    {
        $this->authorizeTenant($course);

        $course->load(['creator', 'modules.lessons']);

        return new CourseResource($course);
    }

    public function update(Request $request, Course $course): CourseResource
    {
        $this->authorizeTenant($course);
        $this->authorize('update', $course);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category'    => ['sometimes', 'in:compliance,technical,soft_skills'],
            'is_active'   => ['boolean'],
        ]);

        $course->update($data);

        return new CourseResource($course->load(['creator', 'modules']));
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorizeTenant($course);
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }

    public function publish(Course $course): CourseResource
    {
        $this->authorizeTenant($course);
        $this->authorize('publish', $course);

        abort_if($course->status !== 'draft', 422, 'Only draft courses can be published.');

        $course->update(['status' => 'published', 'published_at' => now()]);

        return new CourseResource($course);
    }

    public function archive(Course $course): CourseResource
    {
        $this->authorizeTenant($course);
        $this->authorize('publish', $course);

        abort_if($course->status !== 'published', 422, 'Only published courses can be archived.');

        $course->update(['status' => 'archived']);

        return new CourseResource($course);
    }

    private function authorizeTenant(Course $course): void
    {
        abort_if($course->tenant_id !== app('tenant')->id, 403);
    }
}
