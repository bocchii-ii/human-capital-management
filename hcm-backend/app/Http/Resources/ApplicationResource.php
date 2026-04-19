<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'stage'            => $this->stage,
            'cover_letter'     => $this->cover_letter,
            'rejection_reason' => $this->rejection_reason,
            'notes'            => $this->notes,
            'stage_changed_at' => $this->stage_changed_at,
            'applicant'        => new ApplicantResource($this->whenLoaded('applicant')),
            'job_requisition'  => new JobRequisitionResource($this->whenLoaded('jobRequisition')),
            'interviews'       => InterviewResource::collection($this->whenLoaded('interviews')),
            'offer'            => new OfferResource($this->whenLoaded('offer')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
