<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PositionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $positions = Position::where('tenant_id', $tenant->id)
            ->with('department')
            ->withCount('employees')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 15));

        return PositionResource::collection($positions);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage', Position::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description'   => ['nullable', 'string'],
            'level'         => ['nullable', 'string', 'max:100'],
            'is_active'     => ['boolean'],
        ]);

        $position = Position::create(array_merge($data, ['tenant_id' => $tenant->id]));
        $position->load('department');

        return (new PositionResource($position))->response()->setStatusCode(201);
    }

    public function show(Position $position): PositionResource
    {
        $this->authorizeTenant($position);

        $position->load('department')->loadCount('employees');

        return new PositionResource($position);
    }

    public function update(Request $request, Position $position): PositionResource
    {
        $this->authorizeTenant($position);
        $this->authorize('manage', Position::class);

        $data = $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description'   => ['nullable', 'string'],
            'level'         => ['nullable', 'string', 'max:100'],
            'is_active'     => ['boolean'],
        ]);

        $position->update($data);

        return new PositionResource($position->load('department'));
    }

    public function destroy(Position $position): JsonResponse
    {
        $this->authorizeTenant($position);
        $this->authorize('manage', Position::class);

        $position->delete();

        return response()->json(['message' => 'Position deleted.']);
    }

    private function authorizeTenant(Position $position): void
    {
        abort_if($position->tenant_id !== app('tenant')->id, 403);
    }
}
