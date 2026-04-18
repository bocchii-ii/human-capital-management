<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'tenant_id'          => $this->tenant_id,
            'enrollment_id'      => $this->enrollment_id,
            'employee_id'        => $this->employee_id,
            'course_id'          => $this->course_id,
            'certificate_number' => $this->certificate_number,
            'issued_at'          => $this->issued_at,
            'has_pdf'            => $this->pdf_path !== null,
            'employee'           => new EmployeeResource($this->whenLoaded('employee')),
            'course'             => new CourseResource($this->whenLoaded('course')),
            'enrollment'         => new EnrollmentResource($this->whenLoaded('enrollment')),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
