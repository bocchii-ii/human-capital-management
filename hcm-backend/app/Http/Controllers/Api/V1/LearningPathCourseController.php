<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningPathCourseResource;
use App\Models\LearningPath;
use App\Models\LearningPathCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LearningPathCourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LearningPathCourse::class);

        $tenant = app('tenant');

        $items = LearningPathCourse::whereHas('learningPath', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->when($request->filled('learning_path_id'), fn ($q) => $q->where('learning_path_id', $request->learning_path_id))
            ->with('course')
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return LearningPathCourseResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LearningPathCourse::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'learning_path_id' => ['required', 'integer'],
            'course_id'        => ['required', 'integer'],
            'sort_order'       => ['integer', 'min:0'],
            'is_required'      => ['boolean'],
        ]);

        abort_if(
            ! LearningPath::where('id', $data['learning_path_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Learning path not found in this tenant.'
        );

        $item = LearningPathCourse::create($data);

        return (new LearningPathCourseResource($item->load('course')))->response()->setStatusCode(201);
    }

    public function show(LearningPathCourse $learningPathCourse): LearningPathCourseResource
    {
        $this->authorizeTenant($learningPathCourse);
        $this->authorize('view', $learningPathCourse);

        return new LearningPathCourseResource($learningPathCourse->load('course'));
    }

    public function update(Request $request, LearningPathCourse $learningPathCourse): LearningPathCourseResource
    {
        $this->authorizeTenant($learningPathCourse);
        $this->authorize('update', $learningPathCourse);

        $data = $request->validate([
            'sort_order'  => ['integer', 'min:0'],
            'is_required' => ['boolean'],
        ]);

        $learningPathCourse->update($data);

        return new LearningPathCourseResource($learningPathCourse->load('course'));
    }

    public function destroy(LearningPathCourse $learningPathCourse): JsonResponse
    {
        $this->authorizeTenant($learningPathCourse);
        $this->authorize('delete', $learningPathCourse);

        $learningPathCourse->delete();

        return response()->json(['message' => 'Learning path course removed.']);
    }

    private function authorizeTenant(LearningPathCourse $item): void
    {
        $pathTenantId = LearningPath::find($item->learning_path_id)?->tenant_id;
        abort_if($pathTenantId !== app('tenant')->id, 403);
    }
}
