<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRequestItemRequest;
use App\Http\Resources\RequestItemResource;
use App\Models\RequestItem;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @tags Work and materials
 */
class RequestItemController extends Controller
{
    /**
     * List a request's work and materials.
     *
     * The rows, their totals in euros (without VAT, VAT and with VAT) and the
     * defaults for new rows (VAT rate and hourly rate).
     *
     * @response array{data: list<array<string, mixed>>, totals: array{net: float, vat: float, total: float}, defaults: array{vat_rate: float, hourly_rate: float}}
     */
    public function index(ServiceRequest $serviceRequest): JsonResponse
    {
        return $this->listing($serviceRequest);
    }

    /**
     * Add work or material to a request.
     *
     * Returns the request's rows and totals, like the list.
     */
    public function store(SaveRequestItemRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->items()->create($request->itemData());

        return $this->listing($serviceRequest, 201);
    }

    /**
     * Update a row.
     *
     * Replaces all fields of the row. Returns the request's rows and totals.
     */
    public function update(SaveRequestItemRequest $request, ServiceRequest $serviceRequest, RequestItem $item): JsonResponse
    {
        $item->update($request->itemData());

        return $this->listing($serviceRequest);
    }

    /**
     * Delete a row.
     */
    public function destroy(ServiceRequest $serviceRequest, RequestItem $item): Response
    {
        $item->delete();

        return response()->noContent();
    }

    private function listing(ServiceRequest $serviceRequest, int $status = 200): JsonResponse
    {
        $serviceRequest->load('items');
        $totals = $serviceRequest->totalsCents();

        return response()->json([
            'data' => RequestItemResource::collection($serviceRequest->items),
            'totals' => array_map(fn (int $cents) => $cents / 100, $totals),
            'defaults' => [
                'vat_rate' => config('billing.default_vat_rate'),
                'hourly_rate' => config('billing.default_hourly_rate'),
            ],
        ], $status);
    }
}
