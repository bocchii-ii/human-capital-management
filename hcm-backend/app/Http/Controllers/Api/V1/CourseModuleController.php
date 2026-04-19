<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseModuleResource;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseModuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $modules = CourseModule::where('tenant_id', $tenant->id)
            ->with('lessons')
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return CourseModuleResource::collection($modules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CourseModule::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'course_id'   => ['required', 'exists:courses,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        abort_if(
            ! Course::where('id', $data['course_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Course not found in this tenant.'
        );

        $module = CourseModule::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new CourseModuleResource($module->load('lessons')))->response()->setStatusCode(201);
    }

    public function show(CourseModule $courseModule): CourseModuleResource
    {
        $this->authorizeTenant($courseModule);

        $courseModule->load('lessons');

        return new CourseModuleResource($courseModule);
    }

    public function update(Request $request, CourseModule $courseModule): CourseModuleResource
    {
        $this->authorizeTenant($courseModule);
        $this->authorize('update', $courseModule);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        $courseModule->update($data);

        return new CourseModuleResource($courseModule->load('lessons'));
    }

    public function destroy(CourseModule $courseModule): JsonResponse
    {
        $this->authorizeTenant($courseModule);
        $this->authorize('delete', $courseModule);

        $courseModule->delete();

        return response()->json(['message' => 'Course module deleted.']);
    }

    private function authorizeTenant(CourseModule $module): void
    {
        abort_if($module->tenant_id !== app('tenant')->id, 403);
    }
}
