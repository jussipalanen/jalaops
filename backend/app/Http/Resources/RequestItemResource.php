<?php

namespace App\Http\Resources;

use App\Models\RequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts in euros; they're computed from whole cents.
 *
 * @mixin RequestItem
 */
class RequestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price_cents / 100,
            'vat_rate' => $this->vat_rate_basis_points / 100,
            'net' => $this->netCents() / 100,
            'vat' => $this->vatCents() / 100,
            'total' => $this->totalCents() / 100,
        ];
    }
}
