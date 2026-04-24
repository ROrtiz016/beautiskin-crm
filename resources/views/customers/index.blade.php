@extends('layouts.app')

@php
    $nextDirection = static fn (string $column) => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
    $sortArrow = static fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '';
    $storeHasErrors = $errors->any() && old('form_type') === 'store';
    $updateHasErrors = $errors->any() && old('form_type') === 'update';
    $countryRows = json_decode(file_get_contents(resource_path('data/countries-raw.json')), true, 512, JSON_THROW_ON_ERROR);
    usort($countryRows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
    $usStates = config('us_states');
    asort($usStates);
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Customers</h1>
            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">Search and sort the directory, then open a profile for full history and booking.</p>
        </div>
        <button
            type="button"
            class="crm-btn-primary shrink-0"
            onclick="openCreateModal()"
        >
            + New customer
        </button>
    </div>

    <section class="crm-panel p-5">
        <form method="GET" action="{{ route('customers.index') }}" class="mb-4">
            <input
                name="search"
                value="{{ $search }}"
                placeholder="Search by name, email, or phone"
                class="crm-input"
            >
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-300 bg-slate-50/90 text-sm font-semibold text-slate-700">
                    <tr>
                        <th class="py-2 pr-3 font-medium">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $nextDirection('name')])) }}" class="hover:text-slate-800">
                                Name {{ $sortArrow('name') }}
                            </a>
                        </th>
                        <th class="py-2 pr-3 font-medium">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => 'email', 'direction' => $nextDirection('email')])) }}" class="hover:text-slate-800">
                                Email {{ $sortArrow('email') }}
                            </a>
                        </th>
                        <th class="py-2 pr-3 font-medium">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => 'phone', 'direction' => $nextDirection('phone')])) }}" class="hover:text-slate-800">
                                Phone {{ $sortArrow('phone') }}
                            </a>
                        </th>
                        <th class="py-2 pr-3 font-medium">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => 'date_of_birth', 'direction' => $nextDirection('date_of_birth')])) }}" class="hover:text-slate-800">
                                DOB {{ $sortArrow('date_of_birth') }}
                            </a>
                        </th>
                        <th class="py-2 pr-3 font-medium">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => 'appointments_count', 'direction' => $nextDirection('appointments_count')])) }}" class="hover:text-slate-800">
                                Appointments {{ $sortArrow('appointments_count') }}
                            </a>
                        </th>
                        <th class="py-2 pr-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3 font-medium">
                                <a href="{{ route('customers.show', $customer) }}" class="text-slate-900 hover:text-pink-700 hover:underline">
                                    {{ $customer->first_name }} {{ $customer->last_name }}
                                </a>
                            </td>
                            <td class="py-3 pr-3">{{ $customer->email ?: '-' }}</td>
                            <td class="py-3 pr-3">{{ $customer->phone ?: '-' }}</td>
                            <td class="py-3 pr-3">{{ $customer->date_of_birth?->format('Y-m-d') ?: '-' }}</td>
                            <td class="py-3 pr-3">{{ $customer->appointments_count }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('customers.show', $customer) }}" class="mr-2 text-slate-700 hover:text-slate-900">Profile</a>
                                <button
                                    type="button"
                                    class="mr-2 text-slate-700 hover:text-slate-900"
                                    onclick="openEditModal(this)"
                                    data-id="{{ $customer->id }}"
                                    data-first-name="{{ $customer->first_name }}"
                                    data-last-name="{{ $customer->last_name }}"
                                    data-email="{{ $customer->email }}"
                                    data-phone="{{ $customer->phone }}"
                                    data-date-of-birth="{{ $customer->date_of_birth?->format('Y-m-d') }}"
                                    data-gender="{{ $customer->gender }}"
                                    data-address-line1="{{ e($customer->address_line1 ?? '') }}"
                                    data-address-line2="{{ e($customer->address_line2 ?? '') }}"
                                    data-city="{{ e($customer->city ?? '') }}"
                                    data-state-region="{{ e($customer->state_region ?? '') }}"
                                    data-postal-code="{{ e($customer->postal_code ?? '') }}"
                                    data-country="{{ e($customer->country ?? '') }}"
                                    data-notes="{{ e($customer->notes ?? '') }}"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:text-red-700" onclick="return confirm('Delete this customer?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </section>

    <div id="createModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Customer</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreateModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('customers.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="store">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">First name</label>
                        <input name="first_name" value="{{ old('first_name') }}" class="crm-input" required>
                        @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Last name</label>
                        <input name="last_name" value="{{ old('last_name') }}" class="crm-input" required>
                        @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="crm-input">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input name="phone" value="{{ old('phone') }}" class="crm-input">
                        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Date of birth</label>
                        <input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" class="crm-input">
                        @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Gender</label>
                        <input name="gender" value="{{ old('gender') }}" class="crm-input">
                        @error('gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                    <p class="text-sm font-semibold text-slate-800">Address</p>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Street line 1</label>
                        <input name="address_line1" value="{{ old('address_line1') }}" class="crm-input" autocomplete="address-line1">
                        @error('address_line1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Street line 2</label>
                        <input name="address_line2" value="{{ old('address_line2') }}" class="crm-input" autocomplete="address-line2">
                        @error('address_line2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input name="city" value="{{ old('city') }}" class="crm-input" autocomplete="address-level2">
                            @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">State</label>
                            <select name="state_region" class="crm-input" autocomplete="address-level1">
                                <option value="">—</option>
                                @foreach ($usStates as $code => $name)
                                    <option value="{{ $code }}" @selected(old('state_region') === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('state_region') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Postal code</label>
                            <input name="postal_code" value="{{ old('postal_code') }}" class="crm-input" autocomplete="postal-code">
                            @error('postal_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Country</label>
                            <select name="country" class="crm-input" autocomplete="country-name">
                                <option value="">—</option>
                                @foreach ($countryRows as $row)
                                    @php
                                        $code = $row['alpha-2'];
                                        $label = $code === 'US' ? 'United States' : $row['name'];
                                    @endphp
                                    <option value="{{ $code }}" @selected(old('country', 'US') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('country') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Save customer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Customer</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditModal()">✕</button>
            </div>
            <form id="editForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_type" value="update">
                <input type="hidden" id="edit_customer_id" name="customer_id" value="{{ old('customer_id') }}">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">First name</label>
                        <input id="edit_first_name" name="first_name" value="{{ old('first_name') }}" class="crm-input" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Last name</label>
                        <input id="edit_last_name" name="last_name" value="{{ old('last_name') }}" class="crm-input" required>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input id="edit_email" name="email" type="email" value="{{ old('email') }}" class="crm-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input id="edit_phone" name="phone" value="{{ old('phone') }}" class="crm-input">
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Date of birth</label>
                        <input id="edit_date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" class="crm-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Gender</label>
                        <input id="edit_gender" name="gender" value="{{ old('gender') }}" class="crm-input">
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                    <p class="text-sm font-semibold text-slate-800">Address</p>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Street line 1</label>
                        <input id="edit_address_line1" name="address_line1" value="{{ old('address_line1') }}" class="crm-input" autocomplete="address-line1">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Street line 2</label>
                        <input id="edit_address_line2" name="address_line2" value="{{ old('address_line2') }}" class="crm-input" autocomplete="address-line2">
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">City</label>
                            <input id="edit_city" name="city" value="{{ old('city') }}" class="crm-input" autocomplete="address-level2">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">State</label>
                            <select id="edit_state_region" name="state_region" class="crm-input" autocomplete="address-level1">
                                <option value="">—</option>
                                @foreach ($usStates as $code => $name)
                                    <option value="{{ $code }}" @selected(old('state_region') === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Postal code</label>
                            <input id="edit_postal_code" name="postal_code" value="{{ old('postal_code') }}" class="crm-input" autocomplete="postal-code">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Country</label>
                            <select id="edit_country" name="country" class="crm-input" autocomplete="country-name">
                                <option value="">—</option>
                                @foreach ($countryRows as $row)
                                    @php
                                        $code = $row['alpha-2'];
                                        $label = $code === 'US' ? 'United States' : $row['name'];
                                    @endphp
                                    <option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                </div>
                @if ($updateHasErrors)
                    <p class="text-xs text-red-600">Please review the fields and try again.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Update customer</button>
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

        function openEditModal(button) {
            const id = button.dataset.id;
            editForm.action = `/customers/${id}`;
            document.getElementById('edit_customer_id').value = id;
            document.getElementById('edit_first_name').value = button.dataset.firstName || '';
            document.getElementById('edit_last_name').value = button.dataset.lastName || '';
            document.getElementById('edit_email').value = button.dataset.email || '';
            document.getElementById('edit_phone').value = button.dataset.phone || '';
            document.getElementById('edit_date_of_birth').value = button.dataset.dateOfBirth || '';
            document.getElementById('edit_gender').value = button.dataset.gender || '';
            document.getElementById('edit_address_line1').value = button.dataset.addressLine1 || '';
            document.getElementById('edit_address_line2').value = button.dataset.addressLine2 || '';
            document.getElementById('edit_city').value = button.dataset.city || '';
            document.getElementById('edit_state_region').value = button.dataset.stateRegion || '';
            document.getElementById('edit_postal_code').value = button.dataset.postalCode || '';
            document.getElementById('edit_country').value = button.dataset.country || '';
            document.getElementById('edit_notes').value = button.dataset.notes || '';

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
        @if ($updateHasErrors && old('customer_id'))
            editForm.action = `/customers/{{ old('customer_id') }}`;
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        @endif
    </script>
@endsection
