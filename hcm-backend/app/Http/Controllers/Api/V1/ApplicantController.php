<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicantResource;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApplicantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenant = app('tenant');

        $applicants = Applicant::where('tenant_id', $tenant->id)
            ->withCount('applications')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 15));

        return ApplicantResource::collection($applicants);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Applicant::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'source'       => ['nullable', 'string', 'max:100'],
        ]);

        $applicant = Applicant::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return (new ApplicantResource($applicant))->response()->setStatusCode(201);
    }

    public function show(Applicant $applicant): ApplicantResource
    {
        $this->authorizeTenant($applicant);
        $applicant->loadCount('applications');

        return new ApplicantResource($applicant);
    }

    public function update(Request $request, Applicant $applicant): ApplicantResource
    {
        $this->authorizeTenant($applicant);
        $this->authorize('update', $applicant);

        $data = $request->validate([
            'first_name'   => ['sometimes', 'string', 'max:100'],
            'last_name'    => ['sometimes', 'string', 'max:100'],
            'email'        => ['sometimes', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'source'       => ['nullable', 'string', 'max:100'],
        ]);

        $applicant->update($data);

        return new ApplicantResource($applicant);
    }

    public function destroy(Applicant $applicant): JsonResponse
    {
        $this->authorizeTenant($applicant);
        $this->authorize('delete', $applicant);

        $applicant->delete();

        return response()->json(['message' => 'Applicant deleted.']);
    }

    private function authorizeTenant(Applicant $applicant): void
    {
        abort_if($applicant->tenant_id !== app('tenant')->id, 403);
    }
}
