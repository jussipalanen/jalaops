<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creating (POST) and fully updating (PUT) a service request.
 */
class SaveServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(RequestPriority::class)],
            'status' => ['required', Rule::enum(RequestStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'street_address' => ['nullable', 'string', 'max:255'],
            // Mostly Finnish five-digit codes, but other countries' formats are allowed.
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'city' => ['nullable', 'string', 'max:100'],
            // A point needs both coordinates, so one is required when the other is given.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
