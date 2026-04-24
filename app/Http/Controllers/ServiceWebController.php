<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Service;
use App\Support\AppointmentFormLookupCache;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWebController extends Controller
{
    public function index(Request $request): View
    {
        return view('services.index', $this->servicesIndexPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function servicesIndexPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));

        $services = Service::query()
            ->with(['staffUsers', 'coveredByMemberships'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        $staffUsers = AppointmentFormLookupCache::staffUsers();
        $memberships = Membership::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $lowStockServices = Service::query()
            ->lowStock()
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return [
            'services' => $services,
            'staffUsers' => $staffUsers,
            'memberships' => $memberships,
            'search' => $search,
            'lowStockServices' => $lowStockServices,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedServicePayload($request);

        $service = Service::query()->create($validated['attributes']);
        $service->staffUsers()->sync($validated['staff_user_ids']);
        $service->coveredByMemberships()->sync($validated['membership_ids']);

        return redirect()
            ->route('services.index')
            ->with('status', 'Service created successfully.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $this->validatedServicePayload($request);

        $service->update($validated['attributes']);
        $service->staffUsers()->sync($validated['staff_user_ids']);
        $service->coveredByMemberships()->sync($validated['membership_ids']);

        return redirect()
            ->route('services.index')
            ->with('status', 'Service updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        try {
            $service->delete();
        } catch (QueryException) {
            return redirect()
                ->route('services.index')
                ->with('error', 'This service cannot be deleted because it is linked to past appointments. You can mark it inactive instead.');
        }

        return redirect()
            ->route('services.index')
            ->with('status', 'Service deleted successfully.');
    }

    /**
     * @return array{attributes: array<string, mixed>, staff_user_ids: array<int, int>, membership_ids: array<int, int>}
     */
    private function validatedServicePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'staff_user_ids' => ['nullable', 'array'],
            'staff_user_ids.*' => ['integer', 'exists:users,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'exists:memberships,id'],
        ]);

        $staffUserIds = array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])));
        $membershipIds = array_values(array_unique(array_map('intval', $validated['membership_ids'] ?? [])));

        $trackInventory = $request->boolean('track_inventory');

        return [
            'attributes' => [
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'price' => number_format((float) $validated['price'], 2, '.', ''),
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'track_inventory' => $trackInventory,
                'stock_quantity' => max(0, (int) ($validated['stock_quantity'] ?? 0)),
                'reorder_level' => max(0, (int) ($validated['reorder_level'] ?? 5)),
            ],
            'staff_user_ids' => $staffUserIds,
            'membership_ids' => $membershipIds,
        ];
    }
}
