@extends('layouts.app')

@php
    $statusBadge = static function (?string $status): string {
        return match ($status) {
            'completed' => 'bg-emerald-100 text-emerald-700',
            'booked' => 'bg-blue-100 text-blue-700',
            'cancelled' => 'bg-rose-100 text-rose-700',
            'no_show' => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-700',
        };
    };
    $serviceOptions = $services->map(function ($service) {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => (float) $service->price,
        ];
    })->values();
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ $customer->first_name }} {{ $customer->last_name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Customer profile and history</p>
        </div>
        <a href="{{ route('customers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Back to Customers
        </a>
    </div>

    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Email</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit email" onclick="openContactEditModal('email')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->email ?: '-' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit phone" onclick="openContactEditModal('phone')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->phone ?: '-' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Date of Birth</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit date of birth" onclick="openContactEditModal('date_of_birth')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->date_of_birth?->format('Y-m-d') ?: '-' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total Paid</p>
            <p class="mt-1 font-medium">${{ number_format((float) $totalSpent, 2) }}</p>
        </div>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Next Appointment</h2>
            <div class="mt-4">
                @if ($nextAppointment)
                    <div class="rounded-lg border border-pink-200 bg-pink-50 px-3 py-3">
                        <p class="font-medium">{{ optional($nextAppointment->scheduled_at)->format('Y-m-d g:i A') }}</p>
                        <p class="mt-1">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($nextAppointment->status) }}">
                                {{ ucfirst($nextAppointment->status) }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Staff: {{ $nextAppointment->staffUser?->name ?: 'Unassigned' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Services: {{ $nextAppointment->services->pluck('service_name')->filter()->implode(', ') ?: 'TBD' }}
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                onclick="openUpdateAppointmentModal(this)"
                                data-action="{{ route('customers.appointments.update', [$customer, $nextAppointment]) }}"
                                data-scheduled-at="{{ optional($nextAppointment->scheduled_at)->format('Y-m-d\TH:i') }}"
                                data-ends-at="{{ optional($nextAppointment->ends_at)->format('Y-m-d\TH:i') }}"
                                data-staff-user-id="{{ $nextAppointment->staff_user_id ?? '' }}"
                                data-notes="{{ $nextAppointment->notes }}"
                                data-services='@json($nextAppointment->services->map(fn($s) => ["service_id" => $s->service_id, "quantity" => $s->quantity])->values())'
                            >
                                Update
                            </button>
                            <form method="POST" action="{{ route('customers.appointments.status', [$customer, $nextAppointment]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Mark completed
                                </button>
                            </form>
                            <form method="POST" action="{{ route('customers.appointments.status', [$customer, $nextAppointment]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="cancelled">
                                <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">
                                    Mark cancelled
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No upcoming booked appointment.</p>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Booked Appointments</h2>
                <button
                    type="button"
                    class="rounded-md bg-pink-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-pink-700"
                    onclick="openAddAppointmentModal()"
                >
                    + Add Appointment
                </button>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($bookedAppointments as $appointment)
                    <div class="rounded-lg border border-slate-200 px-3 py-2">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">{{ optional($appointment->scheduled_at)->format('Y-m-d g:i A') }}</p>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($appointment->status) }}">
                                {{ ucfirst($appointment->status) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Staff: {{ $appointment->staffUser?->name ?: 'Unassigned' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Services: {{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'TBD' }}
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                onclick="openUpdateAppointmentModal(this)"
                                data-action="{{ route('customers.appointments.update', [$customer, $appointment]) }}"
                                data-scheduled-at="{{ optional($appointment->scheduled_at)->format('Y-m-d\TH:i') }}"
                                data-ends-at="{{ optional($appointment->ends_at)->format('Y-m-d\TH:i') }}"
                                data-staff-user-id="{{ $appointment->staff_user_id ?? '' }}"
                                data-notes="{{ $appointment->notes }}"
                                data-services='@json($appointment->services->map(fn($s) => ["service_id" => $s->service_id, "quantity" => $s->quantity])->values())'
                            >
                                Update
                            </button>
                            <form method="POST" action="{{ route('customers.appointments.status', [$customer, $appointment]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Mark completed
                                </button>
                            </form>
                            <form method="POST" action="{{ route('customers.appointments.status', [$customer, $appointment]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="cancelled">
                                <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">
                                    Mark cancelled
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No booked appointments scheduled.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div id="contactEditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Contact Details</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeContactEditModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('customers.contact.update', $customer) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Email</label>
                    <input id="contact_email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Phone</label>
                    <input id="contact_phone" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Date of birth</label>
                    <input id="contact_dob" name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeContactEditModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">Past Appointments</h2>
        <div class="mt-4 space-y-3">
            @forelse ($pastAppointments as $appointment)
                <div class="rounded-lg border border-slate-200 px-3 py-2">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">{{ optional($appointment->scheduled_at)->format('Y-m-d g:i A') }}</p>
                        <div class="text-right">
                            <p>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($appointment->status) }}">
                                    {{ ucfirst($appointment->status) }}
                                </span>
                            </p>
                            <p class="text-xs text-slate-500">${{ number_format((float) $appointment->total_amount, 2) }}</p>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Staff: {{ $appointment->staffUser?->name ?: 'Unassigned' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Services: {{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services recorded' }}
                    </p>
                    <div class="mt-3">
                        <button
                            type="button"
                            class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                            onclick="openUpdateAppointmentModal(this)"
                            data-action="{{ route('customers.appointments.update', [$customer, $appointment]) }}"
                            data-scheduled-at="{{ optional($appointment->scheduled_at)->format('Y-m-d\TH:i') }}"
                            data-ends-at="{{ optional($appointment->ends_at)->format('Y-m-d\TH:i') }}"
                            data-staff-user-id="{{ $appointment->staff_user_id ?? '' }}"
                            data-notes="{{ $appointment->notes }}"
                            data-services='@json($appointment->services->map(fn($s) => ["service_id" => $s->service_id, "quantity" => $s->quantity])->values())'
                        >
                            Update
                        </button>
                    </div>
                    @if (in_array($appointment->id, $recentlyChangedAppointmentIds, true))
                        <div class="mt-3">
                            <form method="POST" action="{{ route('customers.appointments.status', [$customer, $appointment]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="booked">
                                <button class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                    Undo to booked
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No past appointments yet.</p>
            @endforelse
        </div>
    </section>

    <div id="addAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Book New Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeAddAppointmentModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('customers.appointments.store', $customer) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">Scheduled At</label>
                    <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Ends At</label>
                    <input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select name="staff_user_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}" {{ (string) old('staff_user_id') === (string) $staff->id ? 'selected' : '' }}>
                                {{ $staff->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium">Services</label>
                        <button type="button" class="text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addServiceRow('addServicesContainer')">
                            + Add service
                        </button>
                    </div>
                    <div id="addServicesContainer" class="space-y-2"></div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeAddAppointmentModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <div id="updateAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Update Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeUpdateAppointmentModal()">✕</button>
            </div>
            <form id="updateAppointmentForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Scheduled At</label>
                    <input id="update_scheduled_at" name="scheduled_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Ends At</label>
                    <input id="update_ends_at" name="ends_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select id="update_staff_user_id" name="staff_user_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium">Services</label>
                        <button type="button" class="text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addServiceRow('updateServicesContainer')">
                            + Add service
                        </button>
                    </div>
                    <div id="updateServicesContainer" class="space-y-2"></div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="update_notes" name="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeUpdateAppointmentModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Payment History</h2>
            <div class="mt-4 space-y-3">
                @forelse ($paymentHistory as $payment)
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                        <div>
                            <p class="font-medium">{{ optional($payment->scheduled_at)->format('Y-m-d g:i A') }}</p>
                            <p class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($payment->status) }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </p>
                        </div>
                        <p class="font-semibold">${{ number_format((float) $payment->total_amount, 2) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No completed payment records yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Services Received</h2>
            <div class="mt-4 space-y-3">
                @forelse ($servicesReceived as $service)
                    <div class="rounded-lg border border-slate-200 px-3 py-2">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">{{ $service->service_name }}</p>
                            <p class="text-sm font-semibold">${{ number_format((float) $service->total_spent, 2) }}</p>
                        </div>
                        <p class="text-xs text-slate-500">
                            Visits: {{ $service->visits }} | Quantity: {{ $service->total_quantity }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No service records yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Current Memberships</h2>
            <div class="mt-4 space-y-3">
                @forelse ($currentMemberships as $membershipRecord)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                        <p class="font-medium">{{ $membershipRecord->membership?->name ?: 'Membership' }}</p>
                        <p class="text-xs text-slate-600">
                            {{ $membershipRecord->start_date?->format('Y-m-d') ?: '-' }}
                            to
                            {{ $membershipRecord->end_date?->format('Y-m-d') ?: 'Present' }}
                            ({{ ucfirst($membershipRecord->status) }})
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No current memberships.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Past Memberships</h2>
            <div class="mt-4 space-y-3">
                @forelse ($pastMemberships as $membershipRecord)
                    <div class="rounded-lg border border-slate-200 px-3 py-2">
                        <p class="font-medium">{{ $membershipRecord->membership?->name ?: 'Membership' }}</p>
                        <p class="text-xs text-slate-600">
                            {{ $membershipRecord->start_date?->format('Y-m-d') ?: '-' }}
                            to
                            {{ $membershipRecord->end_date?->format('Y-m-d') ?: '-' }}
                            ({{ ucfirst($membershipRecord->status) }})
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No past memberships.</p>
                @endforelse
            </div>
        </section>
    </div>

    @if ($customer->notes)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Notes</h2>
            <p class="mt-3 text-sm text-slate-700">{{ $customer->notes }}</p>
        </section>
    @endif

    <script>
        const addAppointmentModal = document.getElementById('addAppointmentModal');
        const updateAppointmentModal = document.getElementById('updateAppointmentModal');
        const updateAppointmentForm = document.getElementById('updateAppointmentForm');
        const contactEditModal = document.getElementById('contactEditModal');
        const servicesOptions = @json($serviceOptions);

        function openAddAppointmentModal() {
            const container = document.getElementById('addServicesContainer');
            container.innerHTML = '';
            addServiceRow('addServicesContainer');
            addAppointmentModal.classList.remove('hidden');
            addAppointmentModal.classList.add('flex');
        }

        function closeAddAppointmentModal() {
            addAppointmentModal.classList.add('hidden');
            addAppointmentModal.classList.remove('flex');
        }

        function openUpdateAppointmentModal(button) {
            updateAppointmentForm.action = button.dataset.action || '';
            document.getElementById('update_scheduled_at').value = button.dataset.scheduledAt || '';
            document.getElementById('update_ends_at').value = button.dataset.endsAt || '';
            document.getElementById('update_staff_user_id').value = button.dataset.staffUserId || '';
            document.getElementById('update_notes').value = button.dataset.notes || '';
            const updateContainer = document.getElementById('updateServicesContainer');
            updateContainer.innerHTML = '';
            const serviceLines = safeParseServices(button.dataset.services);
            if (serviceLines.length === 0) {
                addServiceRow('updateServicesContainer');
            } else {
                serviceLines.forEach((line) => addServiceRow('updateServicesContainer', line.service_id, line.quantity));
            }

            updateAppointmentModal.classList.remove('hidden');
            updateAppointmentModal.classList.add('flex');
        }

        function closeUpdateAppointmentModal() {
            updateAppointmentModal.classList.add('hidden');
            updateAppointmentModal.classList.remove('flex');
        }

        function openContactEditModal(field) {
            contactEditModal.classList.remove('hidden');
            contactEditModal.classList.add('flex');

            const focusMap = {
                email: 'contact_email',
                phone: 'contact_phone',
                date_of_birth: 'contact_dob',
            };
            const targetId = focusMap[field];
            if (targetId) {
                setTimeout(() => document.getElementById(targetId)?.focus(), 50);
            }
        }

        function closeContactEditModal() {
            contactEditModal.classList.add('hidden');
            contactEditModal.classList.remove('flex');
        }

        function safeParseServices(raw) {
            if (!raw) return [];
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        }

        function serviceOptionsHtml(selectedServiceId) {
            const defaultOption = '<option value="">Select service</option>';
            const options = servicesOptions.map((service) => {
                const selected = String(selectedServiceId || '') === String(service.id) ? 'selected' : '';
                return `<option value="${service.id}" ${selected}>${service.name} ($${service.price.toFixed(2)})</option>`;
            });
            return defaultOption + options.join('');
        }

        function addServiceRow(containerId, serviceId = '', quantity = 1) {
            const container = document.getElementById(containerId);
            const index = container.querySelectorAll('[data-service-row]').length;
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-service-row', 'true');
            wrapper.className = 'grid gap-2 md:grid-cols-[1fr_120px_80px]';
            wrapper.innerHTML = `
                <select name="services[${index}][service_id]" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    ${serviceOptionsHtml(serviceId)}
                </select>
                <input name="services[${index}][quantity]" type="number" min="1" value="${quantity || 1}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Remove</button>
            `;
            wrapper.querySelector('button').addEventListener('click', () => {
                wrapper.remove();
            });
            container.appendChild(wrapper);
        }
    </script>
@endsection
