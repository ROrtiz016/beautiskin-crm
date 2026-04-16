<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Service::query()->with(['staffUsers', 'coveredByMemberships'])->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'staff_user_ids' => ['nullable', 'array'],
            'staff_user_ids.*' => ['integer', 'exists:users,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'exists:memberships,id'],
        ]);

        $staffUserIds = array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])));
        $membershipIds = array_values(array_unique(array_map('intval', $validated['membership_ids'] ?? [])));
        $service = Service::create(Arr::except($validated, ['staff_user_ids', 'membership_ids']));
        $service->staffUsers()->sync($staffUserIds);
        $service->coveredByMemberships()->sync($membershipIds);

        return response()->json($service->fresh()->load(['staffUsers', 'coveredByMemberships']), 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json($service->load(['staffUsers', 'coveredByMemberships']));
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'staff_user_ids' => ['nullable', 'array'],
            'staff_user_ids.*' => ['integer', 'exists:users,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'exists:memberships,id'],
        ]);

        $staffUserIds = array_key_exists('staff_user_ids', $validated)
            ? array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])))
            : null;
        $membershipIds = array_key_exists('membership_ids', $validated)
            ? array_values(array_unique(array_map('intval', $validated['membership_ids'] ?? [])))
            : null;

        $service->update(Arr::except($validated, ['staff_user_ids', 'membership_ids']));

        if ($staffUserIds !== null) {
            $service->staffUsers()->sync($staffUserIds);
        }
        if ($membershipIds !== null) {
            $service->coveredByMemberships()->sync($membershipIds);
        }

        return response()->json($service->fresh()->load(['staffUsers', 'coveredByMemberships']));
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(status: 204);
    }
}
