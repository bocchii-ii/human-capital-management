<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterviewResource;
use App\Models\Application;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InterviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $interviews = Interview::where('tenant_id', $tenant->id)
            ->with(['application.applicant', 'interviewer'])
            ->when($request->filled('application_id'), fn ($q) => $q->where('application_id', $request->application_id))
            ->when($request->filled('interviewer_id'), fn ($q) => $q->where('interviewer_id', $request->interviewer_id))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->result))
            ->orderBy('scheduled_at')
            ->paginate($request->integer('per_page', 15));

        return InterviewResource::collection($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Interview::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'application_id'   => ['required', 'exists:applications,id'],
            'interviewer_id'   => ['nullable', 'exists:users,id'],
            'type'             => ['required', 'in:technical,hr,culture,panel'],
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'duration_minutes' => ['integer', 'min:15', 'max:480'],
            'location'         => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string'],
        ]);

        abort_if(
            ! Application::where('id', $data['application_id'])->where('tenant_id', $tenant->id)->exists(),
            422,
            'Application not found in this tenant.'
        );

        $interview = Interview::create(array_merge($data, ['tenant_id' => $tenant->id]));
        $interview->load(['interviewer']);

        return (new InterviewResource($interview))->response()->setStatusCode(201);
    }

    public function show(Interview $interview): InterviewResource
    {
        $this->authorizeTenant($interview);

        $interview->load(['application.applicant', 'interviewer']);

        return new InterviewResource($interview);
    }

    public function update(Request $request, Interview $interview): InterviewResource
    {
        $this->authorizeTenant($interview);
        $this->authorize('update', $interview);

        $data = $request->validate([
            'interviewer_id'   => ['nullable', 'exists:users,id'],
            'type'             => ['in:technical,hr,culture,panel'],
            'scheduled_at'     => ['date'],
            'duration_minutes' => ['integer', 'min:15', 'max:480'],
            'location'         => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string'],
            'result'           => ['nullable', 'in:pass,fail,pending'],
            'feedback'         => ['nullable', 'string'],
        ]);

        $interview->update($data);

        return new InterviewResource($interview->load(['application.applicant', 'interviewer']));
    }

    public function destroy(Interview $interview): JsonResponse
    {
        $this->authorizeTenant($interview);
        $this->authorize('delete', $interview);

        $interview->delete();

        return response()->json(['message' => 'Interview deleted.']);
    }

    private function authorizeTenant(Interview $interview): void
    {
        abort_if($interview->tenant_id !== app('tenant')->id, 403);
    }
}
