<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Certificate::class);

        $user  = $request->user();
        $query = Certificate::where('tenant_id', app('tenant')->id)
            ->with(['employee', 'course']);

        if (! $user->can('training.enrollment.manage')) {
            $query->where('employee_id', $user->employee?->id);
        }

        return CertificateResource::collection(
            $query->latest('issued_at')->paginate($request->integer('per_page', 15))
        );
    }

    public function show(Request $request, Certificate $certificate): CertificateResource
    {
        $this->authorize('view', $certificate);

        $certificate->load(['employee', 'course', 'enrollment']);

        return new CertificateResource($certificate);
    }

    public function download(Request $request, Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        abort_unless(
            $certificate->pdf_path && \Illuminate\Support\Facades\Storage::exists($certificate->pdf_path),
            404,
            'Certificate PDF not available.'
        );

        return \Illuminate\Support\Facades\Storage::download(
            $certificate->pdf_path,
            $certificate->certificate_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
