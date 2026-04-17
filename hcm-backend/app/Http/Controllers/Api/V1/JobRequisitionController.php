<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobRequisitionResource;
use App\Models\JobRequisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobRequisitionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $requisitions = JobRequisition::where('tenant_id', $tenant->id)
            ->with(['department', 'position', 'hiringManager'])
            ->withCount('applications')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return JobRequisitionResource::collection($requisitions);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', JobRequisition::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'requirements'       => ['nullable', 'string'],
            'department_id'      => ['nullable', 'exists:departments,id'],
            'position_id'        => ['nullable', 'exists:positions,id'],
            'hiring_manager_id'  => ['nullable', 'exists:users,id'],
            'employment_type'    => ['in:full_time,part_time,contract'],
            'work_location'      => ['nullable', 'string', 'max:255'],
            'is_remote'          => ['boolean'],
            'headcount'          => ['integer', 'min:1'],
            'salary_min'         => ['nullable', 'numeric', 'min:0'],
            'salary_max'         => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'currency'           => ['string', 'size:3'],
        ]);

        $requisition = JobRequisition::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'status'    => 'draft',
        ]));

        $requisition->load(['department', 'position', 'hiringManager']);

        return (new JobRequisitionResource($requisition))->response()->setStatusCode(201);
    }

    public function show(JobRequisition $jobRequisition): JobRequisitionResource
    {
        $this->authorizeTenant($jobRequisition);

        $jobRequisition->load(['department', 'position', 'hiringManager', 'approvedBy'])
            ->loadCount('applications');

        return new JobRequisitionResource($jobRequisition);
    }

    public function update(Request $request, JobRequisition $jobRequisition): JobRequisitionResource
    {
        $this->authorizeTenant($jobRequisition);
        $this->authorize('update', $jobRequisition);

        $data = $request->validate([
            'title'             => ['sometimes', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'requirements'      => ['nullable', 'string'],
            'department_id'     => ['nullable', 'exists:departments,id'],
            'position_id'       => ['nullable', 'exists:positions,id'],
            'hiring_manager_id' => ['nullable', 'exists:users,id'],
            'employment_type'   => ['in:full_time,part_time,contract'],
            'work_location'     => ['nullable', 'string', 'max:255'],
            'is_remote'         => ['boolean'],
            'headcount'         => ['integer', 'min:1'],
            'salary_min'        => ['nullable', 'numeric', 'min:0'],
            'salary_max'        => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['string', 'size:3'],
            'status'            => ['in:draft,open,closed,cancelled'],
        ]);

        $jobRequisition->update($data);

        return new JobRequisitionResource($jobRequisition->load(['department', 'position', 'hiringManager']));
    }

    public function approve(JobRequisition $jobRequisition): JobRequisitionResource
    {
        $this->authorizeTenant($jobRequisition);
        $this->authorize('approve', $jobRequisition);

        abort_if($jobRequisition->status !== 'draft', 422, 'Only draft requisitions can be approved.');

        $jobRequisition->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return new JobRequisitionResource($jobRequisition);
    }

    public function destroy(JobRequisition $jobRequisition): JsonResponse
    {
        $this->authorizeTenant($jobRequisition);
        $this->authorize('delete', $jobRequisition);

        $jobRequisition->delete();

        return response()->json(['message' => 'Job requisition deleted.']);
    }

    private function authorizeTenant(JobRequisition $jobRequisition): void
    {
        abort_if($jobRequisition->tenant_id !== app('tenant')->id, 403);
    }
}
