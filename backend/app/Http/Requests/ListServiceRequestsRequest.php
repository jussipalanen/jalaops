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
            /** Only requests whose title, street address, postal code or city contains this text. */
            'search' => ['nullable', 'string', 'max:100'],
            /**
             * Only requests due in this period: `overdue` (not completed and due before today),
             * `today`, `week` (today and the next 7 days), `month` (the next 30 days) or `none` (no due date).
             */
            'due' => ['nullable', Rule::in(['overdue', 'today', 'week', 'month', 'none'])],
            /** Only requests due on or after this date (YYYY-MM-DD). */
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            /** Only requests due on or before this date (YYYY-MM-DD). */
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
        ];
    }
}
