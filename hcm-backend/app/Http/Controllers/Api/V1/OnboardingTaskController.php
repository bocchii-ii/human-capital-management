<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingTaskResource;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OnboardingTaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $tasks = OnboardingTask::where('tenant_id', $tenant->id)
            ->when($request->filled('onboarding_template_id'), fn ($q) => $q->where('onboarding_template_id', $request->onboarding_template_id))
            ->when($request->filled('assignee_role'), fn ($q) => $q->where('assignee_role', $request->assignee_role))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 50));

        return OnboardingTaskResource::collection($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OnboardingTask::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'onboarding_template_id' => ['required', 'exists:onboarding_templates,id'],
            'title'                  => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'assignee_role'          => ['required', Rule::in(['new_hire', 'hr', 'manager', 'it'])],
            'due_days_offset'        => ['integer', 'min:0'],
            'is_required'            => ['boolean'],
            'sort_order'             => ['integer', 'min:0'],
        ]);

        $template = OnboardingTemplate::findOrFail($data['onboarding_template_id']);
        abort_if($template->tenant_id !== $tenant->id, 403);

        $task = OnboardingTask::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new OnboardingTaskResource($task))->response()->setStatusCode(201);
    }

    public function show(OnboardingTask $onboardingTask): OnboardingTaskResource
    {
        $this->authorizeTenant($onboardingTask);

        return new OnboardingTaskResource($onboardingTask);
    }

    public function update(Request $request, OnboardingTask $onboardingTask): OnboardingTaskResource
    {
        $this->authorizeTenant($onboardingTask);
        $this->authorize('update', $onboardingTask);

        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'assignee_role'  => ['sometimes', Rule::in(['new_hire', 'hr', 'manager', 'it'])],
            'due_days_offset' => ['integer', 'min:0'],
            'is_required'    => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ]);

        $onboardingTask->update($data);

        return new OnboardingTaskResource($onboardingTask);
    }

    public function destroy(OnboardingTask $onboardingTask): JsonResponse
    {
        $this->authorizeTenant($onboardingTask);
        $this->authorize('delete', $onboardingTask);

        $onboardingTask->delete();

        return response()->json(['message' => 'Onboarding task deleted.']);
    }

    private function authorizeTenant(OnboardingTask $task): void
    {
        abort_if($task->tenant_id !== app('tenant')->id, 403);
    }
}
