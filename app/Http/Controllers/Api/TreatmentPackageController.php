<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentPackage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TreatmentPackageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $package = TreatmentPackage::query()->create($validated['attributes']);
        $package->services()->sync($validated['sync']);

        return response()->json($this->packageResponse($package), 201);
    }

    public function update(Request $request, TreatmentPackage $treatmentPackage): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $treatmentPackage->update($validated['attributes']);
        $treatmentPackage->services()->sync($validated['sync']);

        return response()->json($this->packageResponse($treatmentPackage->fresh()));
    }

    public function destroy(TreatmentPackage $treatmentPackage): JsonResponse|Response
    {
        try {
            $treatmentPackage->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'This package cannot be deleted because it is referenced by quotes. You can mark it inactive instead.',
            ], 422);
        }

        return response()->noContent();
    }

    private function packageResponse(TreatmentPackage $package): TreatmentPackage
    {
        return $package->load(['services' => fn ($q) => $q->orderBy('name')]);
    }

    /**
     * @return array{attributes: array<string, mixed>, sync: array<int, array{quantity: int}>}
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'package_price' => ['required', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sync = [];
        foreach ($validated['items'] ?? [] as $row) {
            $sid = (int) $row['service_id'];
            $sync[$sid] = ['quantity' => (int) $row['quantity']];
        }

        return [
            'attributes' => [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'package_price' => number_format((float) $validated['package_price'], 2, '.', ''),
                'is_active' => $request->boolean('is_active'),
            ],
            'sync' => $sync,
        ];
    }
}
