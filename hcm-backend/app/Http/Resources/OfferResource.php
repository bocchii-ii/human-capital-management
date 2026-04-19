<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'salary'      => $this->salary,
            'currency'    => $this->currency,
            'start_date'  => $this->start_date?->toDateString(),
            'expires_at'  => $this->expires_at?->toDateString(),
            'status'      => $this->status,
            'letter_path' => $this->letter_path,
            'sent_at'     => $this->sent_at,
            'signed_at'   => $this->signed_at,
            'notes'       => $this->notes,
            'application' => new ApplicationResource($this->whenLoaded('application')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
