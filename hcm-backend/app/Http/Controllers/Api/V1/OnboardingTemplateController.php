<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingTemplateResource;
use App\Models\OnboardingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OnboardingTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $templates = OnboardingTemplate::where('tenant_id', $tenant->id)
            ->with(['department', 'position'])
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 15));

        return OnboardingTemplateResource::collection($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OnboardingTemplate::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id'   => ['nullable', 'exists:positions,id'],
            'is_active'     => ['boolean'],
        ]);

        $template = OnboardingTemplate::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new OnboardingTemplateResource($template->load(['department', 'position'])))->response()->setStatusCode(201);
    }

    public function show(OnboardingTemplate $onboardingTemplate): OnboardingTemplateResource
    {
        $this->authorizeTenant($onboardingTemplate);

        $onboardingTemplate->load(['department', 'position', 'tasks']);

        return new OnboardingTemplateResource($onboardingTemplate);
    }

    public function update(Request $request, OnboardingTemplate $onboardingTemplate): OnboardingTemplateResource
    {
        $this->authorizeTenant($onboardingTemplate);
        $this->authorize('update', $onboardingTemplate);

        $data = $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id'   => ['nullable', 'exists:positions,id'],
            'is_active'     => ['boolean'],
        ]);

        $onboardingTemplate->update($data);

        return new OnboardingTemplateResource($onboardingTemplate->load(['department', 'position', 'tasks']));
    }

    public function destroy(OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        $this->authorizeTenant($onboardingTemplate);
        $this->authorize('delete', $onboardingTemplate);

        $onboardingTemplate->delete();

        return response()->json(['message' => 'Onboarding template deleted.']);
    }

    private function authorizeTenant(OnboardingTemplate $template): void
    {
        abort_if($template->tenant_id !== app('tenant')->id, 403);
    }
}
