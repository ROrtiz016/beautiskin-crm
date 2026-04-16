@extends('layouts.app')

@php
    $nextDirection = static fn (string $column) => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
    $sortArrow = static fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '';
    $storeHasErrors = $errors->any() && old('form_type') === 'store';
    $updateHasErrors = $errors->any() && old('form_type') === 'update';
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Customers</h1>
            <p class="mt-1 text-sm text-slate-600">Manage clinic customer records.</p>
        </div>
        <button
            type="button"
            class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
            onclick="openCreateModal()"
        >
            + New Customer
        </button>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('customers.index') }}" class="mb-4">
            <input
                name="search"
                value="{{ $search }}"
                placeholder="Search by name, email, or phone"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
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
                                    data-notes="{{ $customer->notes }}"
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

    <div id="createModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl">
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
                        <input name="first_name" value="{{ old('first_name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Last name</label>
                        <input name="last_name" value="{{ old('last_name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input name="phone" value="{{ old('phone') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Date of birth</label>
                        <input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Gender</label>
                        <input name="gender" value="{{ old('gender') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeCreateModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save customer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl">
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
                        <input id="edit_first_name" name="first_name" value="{{ old('first_name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Last name</label>
                        <input id="edit_last_name" name="last_name" value="{{ old('last_name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input id="edit_email" name="email" type="email" value="{{ old('email') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Phone</label>
                        <input id="edit_phone" name="phone" value="{{ old('phone') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Date of birth</label>
                        <input id="edit_date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Gender</label>
                        <input id="edit_gender" name="gender" value="{{ old('gender') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
                @if ($updateHasErrors)
                    <p class="text-xs text-red-600">Please review the fields and try again.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeEditModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Update customer</button>
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
