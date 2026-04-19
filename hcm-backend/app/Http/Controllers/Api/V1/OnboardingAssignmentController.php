<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingAssignmentResource;
use App\Http\Resources\OnboardingTaskCompletionResource;
use App\Models\Employee;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskCompletion;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OnboardingAssignmentController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $assignments = OnboardingAssignment::where('tenant_id', $tenant->id)
            ->with(['employee', 'template'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return OnboardingAssignmentResource::collection($assignments);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OnboardingAssignment::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'employee_id'            => ['required', 'exists:employees,id'],
            'onboarding_template_id' => ['required', 'exists:onboarding_templates,id'],
            'start_date'             => ['required', 'date'],
        ]);

        $assignment = OnboardingAssignment::create(array_merge($data, [
            'tenant_id'   => $tenant->id,
            'assigned_by' => $request->user()->id,
            'status'      => 'pending',
        ]));

        $assignment->load(['employee', 'template', 'assignedBy']);

        $this->audit->log('onboarding.assignment.created', $assignment, null, ['status' => 'pending']);

        $employee = $assignment->employee;
        if ($employee?->user_id) {
            $templateName = $assignment->template?->name ?? 'an onboarding checklist';
            $this->notifications->create(
                $tenant->id,
                $employee->user_id,
                'onboarding.assigned',
                'Onboarding Checklist Assigned',
                "You have been assigned \"{$templateName}\". Welcome!",
                ['assignment_id' => $assignment->id],
            );
        }

        return (new OnboardingAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function show(OnboardingAssignment $onboardingAssignment): OnboardingAssignmentResource
    {
        $this->authorizeTenant($onboardingAssignment);

        $onboardingAssignment->load(['employee', 'template.tasks', 'assignedBy', 'taskCompletions.task', 'taskCompletions.completedBy']);

        return new OnboardingAssignmentResource($onboardingAssignment);
    }

    public function update(Request $request, OnboardingAssignment $onboardingAssignment): OnboardingAssignmentResource
    {
        $this->authorizeTenant($onboardingAssignment);
        $this->authorize('update', $onboardingAssignment);

        $data = $request->validate([
            'status'     => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'start_date' => ['sometimes', 'date'],
        ]);

        if (isset($data['status']) && $data['status'] === 'completed' && !$onboardingAssignment->completed_at) {
            $data['completed_at'] = now();
        }

        $onboardingAssignment->update($data);

        return new OnboardingAssignmentResource($onboardingAssignment->load(['employee', 'template']));
    }

    public function completeTask(Request $request, OnboardingAssignment $onboardingAssignment, OnboardingTask $onboardingTask): \Illuminate\Http\JsonResponse
    {
        $this->authorizeTenant($onboardingAssignment);
        $this->authorize('update', $onboardingAssignment);

        abort_if($onboardingAssignment->onboarding_template_id !== $onboardingTask->onboarding_template_id, 422, 'Task does not belong to this assignment\'s template.');

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $completion = OnboardingTaskCompletion::firstOrCreate(
            [
                'onboarding_assignment_id' => $onboardingAssignment->id,
                'onboarding_task_id'       => $onboardingTask->id,
            ],
            array_merge($data, [
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ])
        );

        // Auto-advance status to in_progress once first task is completed
        if ($onboardingAssignment->status === 'pending') {
            $onboardingAssignment->update(['status' => 'in_progress']);
        }

        return (new OnboardingTaskCompletionResource($completion->load(['task', 'completedBy'])))->response()->setStatusCode(200);
    }

    public function destroy(OnboardingAssignment $onboardingAssignment): JsonResponse
    {
        $this->authorizeTenant($onboardingAssignment);
        $this->authorize('delete', $onboardingAssignment);

        $onboardingAssignment->delete();

        return response()->json(['message' => 'Onboarding assignment deleted.']);
    }

    private function authorizeTenant(OnboardingAssignment $assignment): void
    {
        abort_if($assignment->tenant_id !== app('tenant')->id, 403);
    }
}
