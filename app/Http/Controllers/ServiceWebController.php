<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWebController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $services = Service::query()
            ->with('staffUsers')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        $staffUsers = User::query()->orderBy('name')->get(['id', 'name']);

        return view('services.index', [
            'services' => $services,
            'staffUsers' => $staffUsers,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedServicePayload($request);

        $service = Service::query()->create($validated['attributes']);
        $service->staffUsers()->sync($validated['staff_user_ids']);

        return redirect()
            ->route('services.index')
            ->with('status', 'Service created successfully.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $this->validatedServicePayload($request);

        $service->update($validated['attributes']);
        $service->staffUsers()->sync($validated['staff_user_ids']);

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
     * @return array{attributes: array<string, mixed>, staff_user_ids: array<int, int>}
     */
    private function validatedServicePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'staff_user_ids' => ['nullable', 'array'],
            'staff_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $staffUserIds = array_values(array_unique(array_map('intval', $validated['staff_user_ids'] ?? [])));

        return [
            'attributes' => [
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'price' => number_format((float) $validated['price'], 2, '.', ''),
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
            'staff_user_ids' => $staffUserIds,
        ];
    }
}
