<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListServiceRequestsRequest;
use App\Http\Requests\SaveServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Services\RequestExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * @tags Requests
 */
class ServiceRequestController extends Controller
{
    /**
     * List requests.
     *
     * Filter by status, priority and due date, and search by title, street
     * address, postal code or city (`?search=tampere`), with the query parameters.
     * Sort with `sort` (default: due date, soonest first; requests without a due
     * date come last). With `per_page` the
     * result is paginated and has `links` and `meta`; without it, all matching
     * requests are returned.
     */
    public function index(ListServiceRequestsRequest $request): AnonymousResourceCollection
    {
        $requests = $request->filteredRequests();

        $perPage = $request->validated('per_page');

        return ServiceRequestResource::collection(
            $perPage ? $requests->paginate((int) $perPage)->withQueryString() : $requests->get()
        );
    }

    /**
     * Create a request.
     */
    public function store(SaveServiceRequestRequest $request): ServiceRequestResource
    {
        $serviceRequest = new ServiceRequest;
        $serviceRequest->saveWithSignOff($request->validated());

        /** @status 201 */
        return new ServiceRequestResource($serviceRequest->load('customer'));
    }

    /**
     * Get a request.
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        return new ServiceRequestResource($serviceRequest->load('customer'));
    }

    /**
     * Update a request.
     *
     * Replaces all fields, so send the full request. Leave `completion_signature`
     * out to keep the current signature; changing the status away from
     * completed removes the sign-off.
     */
    public function update(SaveServiceRequestRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $serviceRequest->saveWithSignOff($request->validated());

        return new ServiceRequestResource($serviceRequest->load('customer'));
    }

    /**
     * Get a request's signature.
     *
     * The signature drawn when the request was completed, as a PNG image.
     */
    public function signature(ServiceRequest $serviceRequest): Response|JsonResponse
    {
        $png = $serviceRequest->signaturePng();

        if ($png === null) {
            return response()->json(['message' => 'Pyynnöllä ei ole allekirjoitusta.'], 404);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Download a request.
     *
     * The request as a file: `pdf` or `html` (a self-contained page that opens
     * offline). Includes the sign-off with the signature, or empty signature
     * lines for signing a printed copy.
     */
    public function export(ServiceRequest $serviceRequest, string $format, RequestExport $export): Response
    {
        [$content, $type] = $format === 'pdf'
            ? [$export->pdf($serviceRequest), 'application/pdf']
            : [$export->html($serviceRequest), 'text/html; charset=utf-8'];

        return response($content, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $export->filename($serviceRequest, $format)),
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    /**
     * Delete a request.
     */
    public function destroy(ServiceRequest $serviceRequest): Response
    {
        $serviceRequest->delete();

        return response()->noContent();
    }
}
