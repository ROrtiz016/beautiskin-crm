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
            Service::query()->with('staffUsers')->orderBy('name')->get()
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
        ]);

        $staffUserIds = array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])));
        $service = Service::create(Arr::except($validated, ['staff_user_ids']));
        $service->staffUsers()->sync($staffUserIds);

        return response()->json($service->fresh()->load('staffUsers'), 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json($service->load('staffUsers'));
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
        ]);

        $staffUserIds = array_key_exists('staff_user_ids', $validated)
            ? array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])))
            : null;

        $service->update(Arr::except($validated, ['staff_user_ids']));

        if ($staffUserIds !== null) {
            $service->staffUsers()->sync($staffUserIds);
        }

        return response()->json($service->fresh()->load('staffUsers'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(status: 204);
    }
}
