<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(private NotificationService $notificationService) {}
    public function generate(Enrollment $enrollment): Certificate
    {
        $enrollment->loadMissing(['employee', 'course']);

        $existing = Certificate::where('enrollment_id', $enrollment->id)->first();

        $number = $existing?->certificate_number ?? $this->generateNumber();

        $certificate = Certificate::updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'tenant_id'          => $enrollment->tenant_id,
                'employee_id'        => $enrollment->employee_id,
                'course_id'          => $enrollment->course_id,
                'certificate_number' => $number,
                'issued_at'          => now(),
            ]
        );

        $pdf  = Pdf::loadView('pdf.certificate', ['certificate' => $certificate->load(['employee', 'course'])]);
        $path = "certificates/{$certificate->tenant_id}/{$certificate->certificate_number}.pdf";

        Storage::put($path, $pdf->output());

        $certificate->update(['pdf_path' => $path]);

        $userId = $enrollment->employee?->user_id;
        if ($userId) {
            $courseName = $enrollment->course?->title ?? 'a course';
            $this->notificationService->create(
                $certificate->tenant_id,
                $userId,
                'certificate.issued',
                'Certificate Ready',
                "Your certificate for \"{$courseName}\" is ready to download.",
                ['certificate_id' => $certificate->id, 'course_id' => $certificate->course_id],
            );
        }

        return $certificate->fresh();
    }

    private function generateNumber(): string
    {
        return 'CERT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
    }
}
