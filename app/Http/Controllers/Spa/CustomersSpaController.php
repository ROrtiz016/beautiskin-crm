<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\CustomerWebController;
use App\Models\Customer;
use App\Support\CustomerGeo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersSpaController extends CustomerWebController
{
    public function index(Request $request): JsonResponse
    {
        $payload = $this->customersIndexPayload($request);
        /** @var LengthAwarePaginator<int, Customer> $paginator */
        $paginator = $payload['customers'];

        $rows = $paginator->getCollection()->map(function (Customer $customer): array {
            $dobRaw = $customer->getRawOriginal('date_of_birth');
            $dateOfBirth = null;
            if (is_string($dobRaw) && $dobRaw !== '' && ! str_starts_with($dobRaw, '0000')) {
                $dateOfBirth = substr($dobRaw, 0, 10);
            }

            return [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'date_of_birth' => $dateOfBirth,
                'appointments_count' => (int) ($customer->appointments_count ?? 0),
                'created_at' => $customer->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'customers' => [
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'search' => $payload['search'],
            'sort' => $payload['sort'],
            'direction' => $payload['direction'],
        ]);
    }

    public function edit(Customer $customer): JsonResponse
    {
        $data = $customer->only([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'date_of_birth',
            'gender',
            'address_line1',
            'address_line2',
            'city',
            'state_region',
            'postal_code',
            'country',
            'notes',
        ]);
        $data['country'] = CustomerGeo::normalizeCountry($customer->country);

        return response()->json([
            'customer' => $data,
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->customerShowPayload($customer));
    }
}
