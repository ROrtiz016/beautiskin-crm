<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\CustomerWebController;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersSpaController extends CustomerWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->customersIndexPayload($request));
    }

    public function edit(Customer $customer): JsonResponse
    {
        return response()->json([
            'customer' => $customer->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'gender',
                'notes',
            ]),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->customerShowPayload($customer));
    }
}
