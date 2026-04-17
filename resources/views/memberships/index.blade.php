@extends('layouts.app')

@php
    $storeHasErrors = $errors->any() && old('form_type') === 'store';
    $updateHasErrors = $errors->any() && old('form_type') === 'update';
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Memberships</h1>
            <p class="mt-1 text-sm text-slate-600">Manage recurring plans, pricing, renewal period, and availability.</p>
        </div>
        <button
            type="button"
            class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
            onclick="openCreateModal()"
        >
            + New Membership
        </button>
    </div>

    <section class="crm-panel p-5">
        <form method="GET" action="{{ route('memberships.index') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                <input
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by membership name"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
            @if ($search !== '')
                <a href="{{ route('memberships.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Description</th>
                        <th class="py-2 pr-3 font-medium">Price</th>
                        <th class="py-2 pr-3 font-medium">Billing</th>
                        <th class="py-2 pr-3 font-medium">Active</th>
                        <th class="py-2 pr-3 font-medium">Subscribers</th>
                        <th class="py-2 pr-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($memberships as $membership)
                        @php
                            $billingLabel = (int) $membership->billing_cycle_days >= 365 ? 'Yearly' : 'Monthly';
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3 font-medium">{{ $membership->name }}</td>
                            <td class="py-3 pr-3 text-slate-600">{{ $membership->description ?: '—' }}</td>
                            <td class="py-3 pr-3">${{ number_format((float) $membership->monthly_price, 2) }}</td>
                            <td class="py-3 pr-3">{{ $billingLabel }}</td>
                            <td class="py-3 pr-3">
                                @if ($membership->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Yes</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3">{{ $membership->customer_memberships_count }}</td>
                            <td class="py-3 text-right">
                                <button
                                    type="button"
                                    class="mr-2 text-slate-700 hover:text-slate-900"
                                    onclick="openEditModal('membership-payload-{{ $membership->id }}')"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('memberships.destroy', $membership) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700" onclick="return confirm('Delete this membership? This cannot be undone if customers or services still reference it.')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">No memberships yet. Add your first plan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($memberships as $membership)
        @php
            $membershipEditPayload = [
                'id' => $membership->id,
                'name' => $membership->name,
                'description' => $membership->description,
                'price' => (float) $membership->monthly_price,
                'billing_cycle' => (int) $membership->billing_cycle_days >= 365 ? 'yearly' : 'monthly',
                'is_active' => (bool) $membership->is_active,
            ];
        @endphp
        <script type="application/json" id="membership-payload-{{ $membership->id }}">{!! json_encode($membershipEditPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    @endforeach

    <div id="createModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Membership</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreateModal()">X</button>
            </div>
            <form method="POST" action="{{ route('memberships.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="store">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input name="name" value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Price (USD)</label>
                        <input name="price" type="number" step="0.01" min="0" value="{{ old('price', '0.00') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Billing Cycle</label>
                        <select name="billing_cycle" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            <option value="monthly" @selected(old('billing_cycle', 'monthly') === 'monthly')>Monthly</option>
                            <option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                        </select>
                        @error('billing_cycle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((string) old('is_active', '1') === '1')>
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeCreateModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save membership</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Membership</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditModal()">X</button>
            </div>
            <form id="editForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_type" value="update">
                <input type="hidden" id="edit_membership_id" name="membership_id" value="{{ old('membership_id') }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input id="edit_name" name="name" value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Price (USD)</label>
                        <input id="edit_price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Billing Cycle</label>
                        <select id="edit_billing_cycle" name="billing_cycle" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            <option value="monthly" @selected(old('billing_cycle', 'monthly') === 'monthly')>Monthly</option>
                            <option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                        </select>
                        @error('billing_cycle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input id="edit_is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((string) old('is_active', '1') === '1')>
                        Active
                    </label>
                </div>
                @if ($updateHasErrors)
                    <p class="text-xs text-red-600">Please review the fields and try again.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeEditModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Update membership</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const createModal = document.getElementById('createModal');
        const editModal = document.getElementById('editModal');
        const editForm = document.getElementById('editForm');

        function openCreateModal() {
            createModal.classList.remove('hidden');
            createModal.classList.add('flex');
        }

        function closeCreateModal() {
            createModal.classList.add('hidden');
            createModal.classList.remove('flex');
        }

        function openEditModal(payloadElementId) {
            const el = document.getElementById(payloadElementId);
            if (!el) return;
            let payload;
            try {
                payload = JSON.parse(el.textContent);
            } catch {
                return;
            }

            editForm.action = `/memberships/${payload.id}`;
            document.getElementById('edit_membership_id').value = String(payload.id);
            document.getElementById('edit_name').value = payload.name || '';
            document.getElementById('edit_description').value = payload.description || '';
            document.getElementById('edit_price').value = payload.price != null ? String(payload.price) : '';
            document.getElementById('edit_billing_cycle').value = payload.billing_cycle || 'monthly';
            document.getElementById('edit_is_active').checked = Boolean(payload.is_active);

            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        }

        function closeEditModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }

        @if ($storeHasErrors)
            openCreateModal();
        @endif
        @if ($updateHasErrors && old('membership_id'))
            editForm.action = `{{ url('/memberships') }}/{{ old('membership_id') }}`;
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        @endif
    </script>
@endsection
