<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @tags Customers
 */
class CustomerController extends Controller
{
    /**
     * List customers.
     *
     * In alphabetical order, with each customer's number of requests. Search by
     * name, business ID, contact person or city with `search`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /** Text to look for in the name, business ID, contact person or billing city. */
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $customers = Customer::query()
            ->withCount('serviceRequests')
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($query) => $query
                    ->whereRaw("name like ? escape '!'", [$pattern])
                    ->orWhereRaw("business_id like ? escape '!'", [$pattern])
                    ->orWhereRaw("contact_person like ? escape '!'", [$pattern])
                    ->orWhereRaw("billing_city like ? escape '!'", [$pattern]));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CustomerResource::collection($customers);
    }

    /**
     * Create a customer.
     */
    public function store(SaveCustomerRequest $request): CustomerResource
    {
        /** @status 201 */
        return new CustomerResource(Customer::create($request->validated())->loadCount('serviceRequests'));
    }

    /**
     * Get a customer.
     */
    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->loadCount('serviceRequests'));
    }

    /**
     * Update a customer.
     *
     * Replaces all fields, so send the full customer.
     */
    public function update(SaveCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->loadCount('serviceRequests'));
    }

    /**
     * Delete a customer.
     *
     * Only a customer without requests can be deleted.
     */
    public function destroy(Customer $customer): Response|JsonResponse
    {
        if ($customer->serviceRequests()->exists()) {
            return response()->json([
                'message' => 'Asiakasta ei voi poistaa, koska sillä on pyyntöjä. Vaihda pyyntöjen asiakas tai poista pyynnöt ensin.',
            ], 409);
        }

        $customer->delete();

        return response()->noContent();
    }
}
