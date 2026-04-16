@extends('layouts.app')

@php
    $storeHasErrors = $errors->any() && old('form_type') === 'store';
    $updateHasErrors = $errors->any() && old('form_type') === 'update';
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Services</h1>
            <p class="mt-1 text-sm text-slate-600">Treatment catalog, pricing, and which staff can perform each service.</p>
        </div>
        <button
            type="button"
            class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
            onclick="openCreateModal()"
        >
            + New Service
        </button>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('services.index') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                <input
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by service name"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
            @if ($search !== '')
                <a href="{{ route('services.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Category</th>
                        <th class="py-2 pr-3 font-medium">Duration</th>
                        <th class="py-2 pr-3 font-medium">Price</th>
                        <th class="py-2 pr-3 font-medium">Active</th>
                        <th class="py-2 pr-3 font-medium">Staff</th>
                        <th class="py-2 pr-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3 font-medium">{{ $service->name }}</td>
                            <td class="py-3 pr-3">{{ $service->category ?: '—' }}</td>
                            <td class="py-3 pr-3">{{ $service->duration_minutes }} min</td>
                            <td class="py-3 pr-3">${{ number_format((float) $service->price, 2) }}</td>
                            <td class="py-3 pr-3">
                                @if ($service->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Yes</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3 text-xs text-slate-600">
                                {{ $service->staffUsers->pluck('name')->implode(', ') ?: 'None assigned' }}
                            </td>
                            <td class="py-3 text-right">
                                <button
                                    type="button"
                                    class="mr-2 text-slate-700 hover:text-slate-900"
                                    onclick="openEditModal('service-payload-{{ $service->id }}')"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('services.destroy', $service) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700" onclick="return confirm('Delete this service? This cannot be undone if no appointments reference it.')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">No services yet. Add your first treatment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($services as $service)
        @php
            $serviceEditPayload = [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'duration_minutes' => $service->duration_minutes,
                'price' => (float) $service->price,
                'description' => $service->description,
                'is_active' => (bool) $service->is_active,
                'staff_user_ids' => $service->staffUsers->pluck('id')->values()->all(),
            ];
        @endphp
        <script type="application/json" id="service-payload-{{ $service->id }}">{!! json_encode($serviceEditPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    @endforeach

    <div id="createModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Service</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreateModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('services.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="store">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input name="name" value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Category</label>
                        <input name="category" value="{{ old('category') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Facial">
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Duration (minutes)</label>
                        <input name="duration_minutes" type="number" min="1" value="{{ old('duration_minutes', 30) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('duration_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Price (USD)</label>
                    <input name="price" type="number" step="0.01" min="0" value="{{ old('price', '0.00') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff who can provide this service</label>
                    <div class="mt-2 max-h-40 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3">
                        @forelse ($staffUsers as $staff)
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="staff_user_ids[]"
                                    value="{{ $staff->id }}"
                                    @checked(in_array($staff->id, old('staff_user_ids', []), true))
                                >
                                <span>{{ $staff->name }}</span>
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No users in the system. Add staff accounts first.</p>
                        @endforelse
                    </div>
                    @error('staff_user_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('staff_user_ids.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((string) old('is_active', '1') === '1')>
                        Active (bookable)
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeCreateModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save service</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Service</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditModal()">✕</button>
            </div>
            <form id="editForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_type" value="update">
                <input type="hidden" id="edit_service_id" name="service_id" value="{{ old('service_id') }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input id="edit_name" name="name" value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Category</label>
                        <input id="edit_category" name="category" value="{{ old('category') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Duration (minutes)</label>
                        <input id="edit_duration_minutes" name="duration_minutes" type="number" min="1" value="{{ old('duration_minutes') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('duration_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Price (USD)</label>
                    <input id="edit_price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff who can provide this service</label>
                    <div id="editStaffCheckboxes" class="mt-2 max-h-40 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3">
                        @foreach ($staffUsers as $staff)
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    class="edit-staff-cb"
                                    name="staff_user_ids[]"
                                    value="{{ $staff->id }}"
                                    @checked(in_array($staff->id, old('staff_user_ids', []), true))
                                >
                                <span>{{ $staff->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('staff_user_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('staff_user_ids.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input id="edit_is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((string) old('is_active', '1') === '1')>
                        Active (bookable)
                    </label>
                </div>
                @if ($updateHasErrors)
                    <p class="text-xs text-red-600">Please review the fields and try again.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeEditModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Update service</button>
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

            editForm.action = `/services/${payload.id}`;
            document.getElementById('edit_service_id').value = String(payload.id);
            document.getElementById('edit_name').value = payload.name || '';
            document.getElementById('edit_category').value = payload.category || '';
            document.getElementById('edit_duration_minutes').value = payload.duration_minutes ?? 30;
            document.getElementById('edit_price').value = payload.price != null ? String(payload.price) : '';
            document.getElementById('edit_description').value = payload.description || '';
            document.getElementById('edit_is_active').checked = Boolean(payload.is_active);
            const staffIds = Array.isArray(payload.staff_user_ids) ? payload.staff_user_ids.map(Number) : [];
            document.querySelectorAll('.edit-staff-cb').forEach((cb) => {
                cb.checked = staffIds.includes(Number(cb.value));
            });

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
        @if ($updateHasErrors && old('service_id'))
            editForm.action = `{{ url('/services') }}/{{ old('service_id') }}`;
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        @endif
    </script>
@endsection
