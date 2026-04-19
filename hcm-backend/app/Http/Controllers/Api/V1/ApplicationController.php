<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\JobRequisition;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function __construct(private AuditService $audit) {}
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $applications = Application::where('tenant_id', $tenant->id)
            ->with(['applicant', 'jobRequisition'])
            ->when($request->filled('job_requisition_id'), fn ($q) => $q->where('job_requisition_id', $request->job_requisition_id))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->stage))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('applicant', fn ($q) => $q
                ->where('first_name', 'like', "%{$request->search}%")
                ->orWhere('last_name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApplicationResource::collection($applications);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Application::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'job_requisition_id' => ['required', 'exists:job_requisitions,id'],
            'applicant_id'       => ['required', 'exists:applicants,id'],
            'cover_letter'       => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
        ]);

        $application = Application::create(array_merge($data, [
            'tenant_id'        => $tenant->id,
            'stage'            => 'applied',
            'stage_changed_at' => now(),
        ]));

        $application->load(['applicant', 'jobRequisition']);

        return (new ApplicationResource($application))->response()->setStatusCode(201);
    }

    public function show(Application $application): ApplicationResource
    {
        $this->authorizeTenant($application);

        $application->load(['applicant', 'jobRequisition', 'interviews.interviewer', 'offer']);

        return new ApplicationResource($application);
    }

    public function updateStage(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeTenant($application);
        $this->authorize('update', $application);

        $data = $request->validate([
            'stage'            => ['required', Rule::in(Application::STAGES)],
            'rejection_reason' => ['required_if:stage,rejected', 'nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ]);

        $oldStage = $application->stage;
        $application->update(array_merge($data, ['stage_changed_at' => now()]));

        $this->audit->log(
            'application.stage_changed',
            $application,
            ['stage' => $oldStage],
            ['stage' => $data['stage']],
        );

        return new ApplicationResource($application->load(['applicant', 'jobRequisition']));
    }

    public function destroy(Application $application): JsonResponse
    {
        $this->authorizeTenant($application);
        $this->authorize('delete', $application);

        $application->delete();

        return response()->json(['message' => 'Application deleted.']);
    }

    private function authorizeTenant(Application $application): void
    {
        abort_if($application->tenant_id !== app('tenant')->id, 403);
    }
}
