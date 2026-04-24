<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\TreatmentPackage;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreatmentPackageWebController extends Controller
{
    public function index(): View
    {
        return view('packages.index', $this->packagesIndexPayload());
    }

    /**
     * @return array{packages: \Illuminate\Database\Eloquent\Collection<int, TreatmentPackage>, services: \Illuminate\Database\Eloquent\Collection<int, Service>}
     */
    protected function packagesIndexPayload(): array
    {
        $packages = TreatmentPackage::query()
            ->with(['services' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $services = Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']);

        return [
            'packages' => $packages,
            'services' => $services,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $package = TreatmentPackage::query()->create($validated['attributes']);
        $package->services()->sync($validated['sync']);

        return redirect()
            ->route('packages.index')
            ->with('status', 'Package created successfully.');
    }

    public function update(Request $request, TreatmentPackage $treatmentPackage): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $treatmentPackage->update($validated['attributes']);
        $treatmentPackage->services()->sync($validated['sync']);

        return redirect()
            ->route('packages.index')
            ->with('status', 'Package updated successfully.');
    }

    public function destroy(TreatmentPackage $treatmentPackage): RedirectResponse
    {
        try {
            $treatmentPackage->delete();
        } catch (QueryException) {
            return redirect()
                ->route('packages.index')
                ->with('error', 'This package cannot be deleted because it is referenced by quotes. You can mark it inactive instead.');
        }

        return redirect()
            ->route('packages.index')
            ->with('status', 'Package deleted successfully.');
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
