<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipWebController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        return view('memberships.index', $this->membershipsIndexPayload($request));
    }

    /**
     * @return array{memberships: \Illuminate\Database\Eloquent\Collection<int, Membership>, search: string}
     */
    protected function membershipsIndexPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));

        $memberships = Membership::query()
            ->withCount('customerMemberships')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        return [
            'memberships' => $memberships,
            'search' => $search,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        Membership::query()->create($this->validatedMembershipPayload($request));

        return redirect()
            ->route('memberships.index')
            ->with('status', 'Membership created successfully.');
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $membership->update($this->validatedMembershipPayload($request));

        return redirect()
            ->route('memberships.index')
            ->with('status', 'Membership updated successfully.');
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        try {
            $membership->delete();
        } catch (QueryException) {
            return redirect()
                ->route('memberships.index')
                ->with('error', 'This membership cannot be deleted because it is linked to customer subscriptions or service coverage.');
        }

        return redirect()
            ->route('memberships.index')
            ->with('status', 'Membership deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMembershipPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'monthly_price' => number_format((float) $validated['price'], 2, '.', ''),
            'billing_cycle_days' => $validated['billing_cycle'] === 'yearly' ? 365 : 30,
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
