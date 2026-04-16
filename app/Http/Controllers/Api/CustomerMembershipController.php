<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMembershipController extends Controller
{
    public function index(): JsonResponse
    {
        $records = CustomerMembership::query()
            ->with(['customer', 'membership'])
            ->latest()
            ->paginate(20);

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'membership_id' => ['required', 'exists:memberships,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = CustomerMembership::create($validated);

        return response()->json($record->load(['customer', 'membership']), 201);
    }

    public function show(CustomerMembership $customerMembership): JsonResponse
    {
        $customerMembership->load(['customer', 'membership', 'appointments']);

        return response()->json($customerMembership);
    }

    public function update(Request $request, CustomerMembership $customerMembership): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $customerMembership->update($validated);

        return response()->json($customerMembership->fresh()->load(['customer', 'membership']));
    }

    public function destroy(CustomerMembership $customerMembership): JsonResponse
    {
        $customerMembership->delete();

        return response()->json(status: 204);
    }
}
