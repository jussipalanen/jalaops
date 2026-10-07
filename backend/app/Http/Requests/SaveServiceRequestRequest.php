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
        ];
    }
}
