<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            // The invoice the request is billed on, if any.
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? ['id' => $this->invoice->id, 'number' => $this->invoice->number, 'status' => $this->invoice->status] : null),
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'street_address' => $this->street_address,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            // The signature image itself is at /api/requests/{id}/signature, so lists stay small.
            'has_signature' => $this->completion_signature !== null,
            'signed_by' => $this->signed_by,
            'signed_at' => $this->signed_at,
            // Work and materials with their totals in euros, on a single request.
            'items' => RequestItemResource::collection($this->whenLoaded('items')),
            'totals' => $this->whenLoaded('items', fn () => array_map(fn (int $cents) => $cents / 100, $this->totalsCents())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
