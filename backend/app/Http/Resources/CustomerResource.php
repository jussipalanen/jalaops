<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_id' => $this->business_id,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_street_address' => $this->billing_street_address,
            'billing_postal_code' => $this->billing_postal_code,
            'billing_city' => $this->billing_city,
            'payment_terms_days' => $this->payment_terms_days,
            'notes' => $this->notes,
            // How many requests the customer has, when counted (the list and single customers).
            'requests_count' => $this->whenCounted('serviceRequests'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
