@extends('layouts.app')

@php
    $storeHasErrors = $errors->any() && old('form_type') === 'store';
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Treatment packages</h1>
            <p class="mt-1 text-sm text-slate-600">Bundle services at a package price for quotes and in-room explanations.</p>
        </div>
        <button type="button" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700" onclick="openCreatePackageModal()">
            + New package
        </button>
    </div>

    <section class="crm-panel p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Package price</th>
                        <th class="py-2 pr-3 font-medium">Includes</th>
                        <th class="py-2 pr-3 font-medium">Active</th>
                        <th class="py-2 pr-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3 font-medium">{{ $package->name }}</td>
                            <td class="py-3 pr-3">${{ number_format((float) $package->package_price, 2) }}</td>
                            <td class="py-3 pr-3 text-xs text-slate-600">
                                {{ $package->services->map(fn ($s) => $s->name.' ×'.(int) $s->pivot->quantity)->implode(', ') ?: '—' }}
                            </td>
                            <td class="py-3 pr-3">
                                @if ($package->is_active)
                                    <span class="text-xs font-medium text-emerald-800">Yes</span>
                                @else
                                    <span class="text-xs text-slate-500">No</span>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <button type="button" class="mr-2 text-slate-700 hover:text-slate-900" onclick="openEditPackageModal('package-payload-{{ $package->id }}')">Edit</button>
                                <form method="POST" action="{{ route('packages.destroy', $package) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700" onclick="return confirm('Delete this package?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500">No packages yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($packages as $package)
        @php
            $payload = [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'package_price' => (float) $package->package_price,
                'is_active' => (bool) $package->is_active,
                'items' => $package->services->map(fn ($s) => ['service_id' => $s->id, 'quantity' => (int) $s->pivot->quantity])->values()->all(),
            ];
        @endphp
        <script type="application/json" id="package-payload-{{ $package->id }}">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    @endforeach

    <div id="createPackageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add package</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreatePackageModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('packages.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="store">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input name="name" value="{{ old('name') }}" class="crm-input" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea name="description" rows="2" class="crm-input">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Package price (USD)</label>
                    <input name="package_price" type="number" step="0.01" min="0" value="{{ old('package_price', '0') }}" class="crm-input" required>
                    @error('package_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Included services</label>
                    <div id="createPackageItems" class="mt-2 space-y-2"></div>
                    <button type="button" class="mt-2 text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addPackageItemRow('createPackageItems')">+ Add service line</button>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', '1') === '1')>
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeCreatePackageModal()">Cancel</button>
                    <button class="crm-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editPackageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit package</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditPackageModal()">✕</button>
            </div>
            <form id="editPackageForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_type" value="update">
                <input type="hidden" name="id" id="edit_pkg_id" value="">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input id="edit_pkg_name" name="name" class="crm-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea id="edit_pkg_description" name="description" rows="2" class="crm-input"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Package price (USD)</label>
                    <input id="edit_pkg_price" name="package_price" type="number" step="0.01" min="0" class="crm-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Included services</label>
                    <div id="editPackageItems" class="mt-2 space-y-2"></div>
                    <button type="button" class="mt-2 text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addPackageItemRow('editPackageItems')">+ Add service line</button>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input id="edit_pkg_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeEditPackageModal()">Cancel</button>
                    <button class="crm-btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const servicesOptions = @json($services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());

        function packageItemRowHtml(selectedId = '', qty = 1) {
            let opts = '<option value="">Service…</option>';
            servicesOptions.forEach((s) => {
                opts += `<option value="${s.id}" ${String(s.id) === String(selectedId) ? 'selected' : ''}>${s.name}</option>`;
            });
            return `<div class="flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-2">
                <div class="min-w-0 flex-1">
                    <select name="items[][service_id]" class="crm-input text-sm" required>${opts}</select>
                </div>
                <div class="w-24">
                    <label class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Qty</label>
                    <input name="items[][quantity]" type="number" min="1" value="${qty}" class="crm-input text-sm" required>
                </div>
                <button type="button" class="text-xs text-red-600 hover:text-red-800" onclick="this.closest('div').remove()">Remove</button>
            </div>`;
        }

        function addPackageItemRow(containerId) {
            const c = document.getElementById(containerId);
            if (!c) return;
            c.insertAdjacentHTML('beforeend', packageItemRowHtml('', 1));
        }

        function openCreatePackageModal() {
            document.getElementById('createPackageModal').classList.remove('hidden');
            document.getElementById('createPackageModal').classList.add('flex');
            const c = document.getElementById('createPackageItems');
            c.innerHTML = '';
            if (!c.children.length) addPackageItemRow('createPackageItems');
        }

        function closeCreatePackageModal() {
            document.getElementById('createPackageModal').classList.add('hidden');
            document.getElementById('createPackageModal').classList.remove('flex');
        }

        function openEditPackageModal(payloadId) {
            const el = document.getElementById(payloadId);
            if (!el) return;
            let p;
            try { p = JSON.parse(el.textContent); } catch { return; }
            const form = document.getElementById('editPackageForm');
            form.action = `/packages/${p.id}`;
            const hid = document.getElementById('edit_pkg_id');
            if (hid) hid.value = String(p.id);
            document.getElementById('edit_pkg_name').value = p.name || '';
            document.getElementById('edit_pkg_description').value = p.description || '';
            document.getElementById('edit_pkg_price').value = p.package_price != null ? String(p.package_price) : '';
            document.getElementById('edit_pkg_active').checked = Boolean(p.is_active);
            const c = document.getElementById('editPackageItems');
            c.innerHTML = '';
            const items = Array.isArray(p.items) ? p.items : [];
            if (!items.length) {
                addPackageItemRow('editPackageItems');
            } else {
                items.forEach((it) => {
                    c.insertAdjacentHTML('beforeend', packageItemRowHtml(it.service_id, it.quantity || 1));
                });
            }
            document.getElementById('editPackageModal').classList.remove('hidden');
            document.getElementById('editPackageModal').classList.add('flex');
        }

        function closeEditPackageModal() {
            document.getElementById('editPackageModal').classList.add('hidden');
            document.getElementById('editPackageModal').classList.remove('flex');
        }

        @if ($storeHasErrors)
            openCreatePackageModal();
        @endif
    </script>
@endsection
