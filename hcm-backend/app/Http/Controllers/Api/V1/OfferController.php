<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Application;
use App\Models\Offer;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private AuditService $audit) {}
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Offer::class);

        $tenant = app('tenant');

        $data = $request->validate([
            'application_id' => ['required', 'exists:applications,id'],
            'salary'         => ['required', 'numeric', 'min:0'],
            'currency'       => ['string', 'size:3'],
            'start_date'     => ['required', 'date', 'after:today'],
            'expires_at'     => ['nullable', 'date', 'after:today'],
            'notes'          => ['nullable', 'string'],
        ]);

        // Ensure no existing offer for this application
        abort_if(
            Offer::where('application_id', $data['application_id'])->exists(),
            422,
            'An offer already exists for this application.'
        );

        $offer = Offer::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'status'    => 'draft',
        ]));

        $offer->load('application.applicant');

        return (new OfferResource($offer))->response()->setStatusCode(201);
    }

    public function show(Offer $offer): OfferResource
    {
        $this->authorizeTenant($offer);

        $offer->load('application.applicant');

        return new OfferResource($offer);
    }

    public function update(Request $request, Offer $offer): OfferResource
    {
        $this->authorizeTenant($offer);
        $this->authorize('update', $offer);

        $data = $request->validate([
            'salary'     => ['numeric', 'min:0'],
            'currency'   => ['string', 'size:3'],
            'start_date' => ['date'],
            'expires_at' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string'],
        ]);

        $offer->update($data);

        return new OfferResource($offer);
    }

    public function send(Offer $offer): OfferResource
    {
        $this->authorizeTenant($offer);
        $this->authorize('send', $offer);

        abort_if($offer->status !== 'draft', 422, 'Only draft offers can be sent.');

        $offer->update(['status' => 'sent', 'sent_at' => now()]);

        // Advance application to offer stage
        $offer->application->update([
            'stage'            => 'offer',
            'stage_changed_at' => now(),
        ]);

        $this->audit->log('offer.sent', $offer, ['status' => 'draft'], ['status' => 'sent']);

        return new OfferResource($offer);
    }

    public function updateStatus(Request $request, Offer $offer): OfferResource
    {
        $this->authorizeTenant($offer);
        $this->authorize('update', $offer);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,declined,withdrawn'],
        ]);

        $extra = [];
        if ($data['status'] === 'accepted') {
            $extra['signed_at'] = now();
            // Advance application to hired
            $offer->application->update(['stage' => 'hired', 'stage_changed_at' => now()]);
        } elseif ($data['status'] === 'declined') {
            $offer->application->update(['stage' => 'rejected', 'stage_changed_at' => now()]);
        }

        $oldStatus = $offer->status;
        $offer->update(array_merge($data, $extra));

        $this->audit->log(
            "offer.{$data['status']}",
            $offer,
            ['status' => $oldStatus],
            ['status' => $data['status']],
        );

        return new OfferResource($offer);
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $this->authorizeTenant($offer);
        $this->authorize('delete', $offer);

        $offer->delete();

        return response()->json(['message' => 'Offer deleted.']);
    }

    private function authorizeTenant(Offer $offer): void
    {
        abort_if($offer->tenant_id !== app('tenant')->id, 403);
    }
}
