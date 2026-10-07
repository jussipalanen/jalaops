<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     */
    public function index(): AnonymousResourceCollection
    {
        $requests = ServiceRequest::query()
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
