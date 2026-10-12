<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional filters for listing service requests.
 */
class ListServiceRequestsRequest extends FormRequest
{
    public const SORTS = [
        'due_date', '-due_date', 'title', '-title', 'priority', '-priority',
        'status', '-status', 'created_at', '-created_at',
    ];

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
            /**
             * Sort order: `due_date` (the default), `title`, `priority`, `status` or `created_at`,
             * with `-` in front for descending, e.g. `-priority` (high first).
             */
            'sort' => ['nullable', Rule::in(self::SORTS)],
            /** Paginate with this many requests per page. Without it, all matching requests are returned. */
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            /** Page number, with `per_page`. */
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The requests matching the filters and the search, in the chosen order.
     * Shared by the list and the export.
     *
     * @return Builder<ServiceRequest>
     */
    public function filteredRequests(): Builder
    {
        $status = $this->enum('status', RequestStatus::class);
        $priority = $this->enum('priority', RequestPriority::class);
        $search = trim((string) $this->validated('search'));
        $due = $this->validated('due');
        $dueFrom = $this->validated('due_from');
        $dueTo = $this->validated('due_to');

        return ServiceRequest::query()
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($priority, fn ($query) => $query->withPriority($priority))
            ->when($search !== '', fn ($query) => $query->matching($search))
            ->when($due, fn ($query) => $query->dueIn($due))
            ->when($dueFrom || $dueTo, fn ($query) => $query->dueBetween($dueFrom, $dueTo))
            ->sortedBy($this->validated('sort') ?? 'due_date');
    }
}
