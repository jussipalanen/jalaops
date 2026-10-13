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
        if ($locked = $this->lockedResponse($serviceRequest)) {
            return $locked;
        }

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
        if ($locked = $this->lockedResponse($serviceRequest)) {
            return $locked;
        }

        $item->update($request->itemData());

        return $this->listing($serviceRequest);
    }

    /**
     * Delete a row.
     */
    public function destroy(ServiceRequest $serviceRequest, RequestItem $item): Response|JsonResponse
    {
        if ($locked = $this->lockedResponse($serviceRequest)) {
            return $locked;
        }

        $item->delete();

        return response()->noContent();
    }

    /**
     * An invoiced request's rows can't change: the invoice was made from them.
     */
    private function lockedResponse(ServiceRequest $serviceRequest): ?JsonResponse
    {
        if (! $serviceRequest->invoice) {
            return null;
        }

        return response()->json([
            'message' => "Pyyntö on laskulla {$serviceRequest->invoice->number}, joten sen töitä ja tarvikkeita ei voi muuttaa. Poista laskuluonnos ensin, jos rivejä pitää korjata.",
        ], 409);
    }

    private function listing(ServiceRequest $serviceRequest, int $status = 200): JsonResponse
    {
        $serviceRequest->load('items');
        $totals = $serviceRequest->totalsCents();

        return response()->json([
            'data' => RequestItemResource::collection($serviceRequest->items),
            'totals' => array_map(fn (int $cents) => $cents / 100, $totals),
            'locked' => $serviceRequest->invoice_id !== null,
            'defaults' => [
                'vat_rate' => config('billing.default_vat_rate'),
                'hourly_rate' => config('billing.default_hourly_rate'),
            ],
        ], $status);
    }
}
