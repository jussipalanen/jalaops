<?php

namespace App\Http\Requests;

/**
 * Validates exporting requests: the list's filters, or the chosen requests' ids.
 */
class ExportRequestsRequest extends ListServiceRequestsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** `csv` (opens in Excel) or `json` (a full copy, including signatures). */
            'format' => ['required', 'in:csv,json'],
            /** Only these requests, as comma-separated ids, e.g. `1,5,8`. Without it, all requests matching the filters. */
            'ids' => ['nullable', 'string', 'regex:/^\d+(,\d+)*$/'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = $this->validated('ids');

        return $ids ? array_map('intval', explode(',', $ids)) : [];
    }
}
