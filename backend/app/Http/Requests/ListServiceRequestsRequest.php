<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional filters for listing service requests.
 */
class ListServiceRequestsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Only requests with this status. */
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            /** Only requests with this priority. */
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],
        ];
    }
}
