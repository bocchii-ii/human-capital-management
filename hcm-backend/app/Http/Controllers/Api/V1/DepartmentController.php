<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $departments = Department::where('tenant_id', $tenant->id)
            ->withCount(['positions', 'employees'])
            ->with('parent')
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function tree(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $departments = Department::where('tenant_id', $tenant->id)
            ->whereNull('parent_id')
            ->with('children.children')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($departments);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage', Department::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'exists:departments,id'],
            'is_active'   => ['boolean'],
        ]);

        $department = Department::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorizeTenant($department);

        $department->load(['parent', 'children', 'positions'])
            ->loadCount('employees');

        return new DepartmentResource($department);
    }

    public function update(Request $request, Department $department): DepartmentResource
    {
        $this->authorizeTenant($department);
        $this->authorize('manage', Department::class);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'exists:departments,id'],
            'is_active'   => ['boolean'],
        ]);

        $department->update($data);

        return new DepartmentResource($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorizeTenant($department);
        $this->authorize('manage', Department::class);

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }

    private function authorizeTenant(Department $department): void
    {
        abort_if($department->tenant_id !== app('tenant')->id, 403);
    }
}
