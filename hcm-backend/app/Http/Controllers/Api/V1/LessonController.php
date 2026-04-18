<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LessonController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $lessons = Lesson::where('tenant_id', $tenant->id)
            ->when($request->filled('course_module_id'), fn ($q) => $q->where('course_module_id', $request->course_module_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return LessonResource::collection($lessons);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lesson::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'course_module_id' => ['required', 'exists:course_modules,id'],
            'title'            => ['required', 'string', 'max:255'],
            'content_type'     => ['required', 'in:video,pdf,text,quiz'],
            'content'          => ['nullable', 'string'],
            'video_url'        => ['nullable', 'string', 'max:500'],
            'file_url'         => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'sort_order'       => ['integer', 'min:0'],
            'is_required'      => ['boolean'],
        ]);

        abort_if(
            ! CourseModule::where('id', $data['course_module_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Course module not found in this tenant.'
        );

        $lesson = Lesson::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function show(Lesson $lesson): LessonResource
    {
        $this->authorizeTenant($lesson);

        return new LessonResource($lesson);
    }

    public function update(Request $request, Lesson $lesson): LessonResource
    {
        $this->authorizeTenant($lesson);
        $this->authorize('update', $lesson);

        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'content_type'     => ['sometimes', 'in:video,pdf,text,quiz'],
            'content'          => ['nullable', 'string'],
            'video_url'        => ['nullable', 'string', 'max:500'],
            'file_url'         => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'sort_order'       => ['integer', 'min:0'],
            'is_required'      => ['boolean'],
        ]);

        $lesson->update($data);

        return new LessonResource($lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorizeTenant($lesson);
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted.']);
    }

    private function authorizeTenant(Lesson $lesson): void
    {
        abort_if($lesson->tenant_id !== app('tenant')->id, 403);
    }
}
