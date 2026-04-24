<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MembershipController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Membership::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'billing_cycle_days' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(Membership::create($validated), 201);
    }

    public function show(Membership $membership): JsonResponse
    {
        return response()->json($membership);
    }

    public function update(Request $request, Membership $membership): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'monthly_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'billing_cycle_days' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $membership->update($validated);

        return response()->json($membership->fresh());
    }

    public function destroy(Membership $membership): JsonResponse|Response
    {
        try {
            $membership->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'This membership cannot be deleted because it is linked to customer subscriptions or service coverage.',
            ], 422);
        }

        return response()->noContent();
    }
}
