<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListServiceRequestsRequest;
use App\Http\Requests\SaveServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Requests
 */
class ServiceRequestController extends Controller
{
    /**
     * List requests.
     *
     * Sorted by due date, soonest first. Requests without a due date come last.
     * Filter by status and/or priority with the query parameters.
     */
    public function index(ListServiceRequestsRequest $request): AnonymousResourceCollection
    {
        $status = $request->enum('status', RequestStatus::class);
        $priority = $request->enum('priority', RequestPriority::class);

        $requests = ServiceRequest::query()
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($priority, fn ($query) => $query->withPriority($priority))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return ServiceRequestResource::collection($requests);
    }

    /**
     * Create a request.
     */
    public function store(SaveServiceRequestRequest $request): ServiceRequestResource
    {
        /** @status 201 */
        return new ServiceRequestResource(ServiceRequest::create($request->validated()));
    }

    /**
     * Get a request.
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * Update a request.
     *
     * Replaces all fields, so send the full request.
     */
    public function update(SaveServiceRequestRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $serviceRequest->update($request->validated());

        return new ServiceRequestResource($serviceRequest);
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
