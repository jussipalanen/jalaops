<?php

namespace App\Http\Requests;

use App\Rules\BusinessId;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creating (POST) and fully updating (PUT) a customer.
 */
class SaveCustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business_id' => ['nullable', 'string', new BusinessId],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_street_address' => ['nullable', 'string', 'max:255'],
            'billing_postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
