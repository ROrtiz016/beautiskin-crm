@extends('layouts.app')

@php
    $prevMonth = $monthBase->copy()->subMonth()->format('Y-m');
    $nextMonth = $monthBase->copy()->addMonth()->format('Y-m');
    $serviceOptions = $services->map(function ($service) {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => (float) $service->price,
        ];
    })->values();
    $activeChips = [];
    if (!empty($filters['customer_id'])) {
        $selectedCustomer = $customers->firstWhere('id', (int) $filters['customer_id']);
        if ($selectedCustomer) {
            $activeChips[] = ['key' => 'customer_id', 'label' => 'Customer: ' . $selectedCustomer->first_name . ' ' . $selectedCustomer->last_name];
        }
    }
    if (!empty($filters['service_id'])) {
        $selectedService = $services->firstWhere('id', (int) $filters['service_id']);
        if ($selectedService) {
            $activeChips[] = ['key' => 'service_id', 'label' => 'Service: ' . $selectedService->name];
        }
    }
    if (!empty($filters['status'])) {
        $activeChips[] = ['key' => 'status', 'label' => 'Status: ' . ucfirst(str_replace('_', ' ', $filters['status']))];
    }
    if (!empty($filters['arrived'])) {
        $activeChips[] = ['key' => 'arrived', 'label' => 'Arrived: ' . ucfirst($filters['arrived'])];
    }
    if (!empty($filters['staff_user_id'])) {
        $selectedStaff = $staffUsers->firstWhere('id', (int) $filters['staff_user_id']);
        if ($selectedStaff) {
            $activeChips[] = ['key' => 'staff_user_id', 'label' => 'Staff: ' . $selectedStaff->name];
        }
    }
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Appointments</h1>
            <p class="mt-1 text-sm text-slate-600">Manage daily schedule and monthly calendar.</p>
        </div>
        <button type="button" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700" onclick="openAddAppointmentModal()">
            + New Appointment
        </button>
    </div>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('appointments.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-7">
            <input type="hidden" name="month" value="{{ $monthBase->format('Y-m') }}">
            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
            <div class="2xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</label>
                <select name="customer_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (int) $filters['customer_id'] === $customer->id ? 'selected' : '' }}>
                            {{ $customer->first_name }} {{ $customer->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="2xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Service</label>
                <select name="service_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All services</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" {{ (int) $filters['service_id'] === $service->id ? 'selected' : '' }}>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    @foreach (['booked', 'completed', 'cancelled', 'no_show'] as $status)
                        <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Arrived</label>
                <select name="arrived" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="yes" {{ $filters['arrived'] === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no" {{ $filters['arrived'] === 'no' ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</label>
                <select name="staff_user_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All staff</option>
                    @foreach ($staffUsers as $staff)
                        <option value="{{ $staff->id }}" {{ (int) $filters['staff_user_id'] === $staff->id ? 'selected' : '' }}>
                            {{ $staff->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-3 2xl:col-span-7 flex items-center gap-2">
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Apply Filters</button>
                <a href="{{ route('appointments.index', ['month' => $monthBase->format('Y-m'), 'date' => $selectedDate->toDateString()]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </section>

    @if (count($activeChips) > 0)
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active Filters</span>
                @foreach ($activeChips as $chip)
                    @php
                        $chipQuery = array_merge($filters, ['month' => $monthBase->format('Y-m'), 'date' => $selectedDate->toDateString()]);
                        $chipQuery[$chip['key']] = '';
                    @endphp
                    <a
                        href="{{ route('appointments.index', $chipQuery) }}"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    >
                        <span>{{ $chip['label'] }}</span>
                        <span aria-hidden="true">×</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">Today's Appointments ({{ $today->format('Y-m-d') }})</h2>
        <div class="mt-4 space-y-3">
            @forelse ($todaysAppointments as $appointment)
                <div class="rounded-lg border border-slate-200 px-3 py-2">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">{{ optional($appointment->scheduled_at)->format('g:i A') }} - {{ optional($appointment->ends_at)->format('g:i A') ?: 'TBD' }}</p>
                        <p class="text-xs text-slate-500">{{ ucfirst($appointment->status) }}</p>
                    </div>
                    <p class="text-sm">{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}</p>
                    <p class="text-xs text-slate-500">
                        Staff: {{ $appointment->staffUser?->name ?: 'Unassigned' }}
                    </p>
                    <p class="text-xs text-slate-500">
                        Services: {{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services selected' }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No appointments scheduled for today.</p>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <a class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('appointments.index', array_merge($filters, ['month' => $prevMonth, 'date' => $selectedDate->copy()->subMonth()->toDateString()])) }}">
                    ← Previous
                </a>
                <h2 class="text-lg font-semibold">{{ $monthBase->format('F Y') }}</h2>
                <a class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('appointments.index', array_merge($filters, ['month' => $nextMonth, 'date' => $selectedDate->copy()->addMonth()->toDateString()])) }}">
                    Next →
                </a>
            </div>

            <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>

            <div class="mt-2 space-y-2">
                @foreach ($weeks as $week)
                    <div class="grid grid-cols-7 gap-2">
                        @foreach ($week as $day)
                            <a
                                href="{{ route('appointments.index', array_merge($filters, ['month' => $monthBase->format('Y-m'), 'date' => $day['date']->toDateString()])) }}"
                                class="min-h-[74px] rounded-lg border px-2 py-2 text-left transition
                                    {{ $day['in_month'] ? 'border-slate-200 bg-white hover:border-pink-300' : 'border-slate-100 bg-slate-50 text-slate-400' }}
                                    {{ $day['is_selected'] ? 'ring-2 ring-pink-500' : '' }}
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold {{ $day['is_today'] ? 'text-pink-700' : '' }}">{{ $day['date']->day }}</span>
                                    @if ($day['count'] > 0)
                                        <span class="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ $day['count'] }}</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Selected Day</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $selectedDate->format('Y-m-d') }}</p>
            <div class="mt-4 space-y-3">
                @forelse ($selectedAppointments as $appointment)
                    @php
                        $activeMembership = optional($appointment->customer)->memberships
                            ?->first(fn ($membership) => $membership->status === 'active' && (! $membership->end_date || $membership->end_date->isFuture()));
                        $membershipName = $activeMembership?->membership?->name;
                    @endphp
                    <button
                        type="button"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-left hover:border-pink-300 hover:bg-pink-50"
                        onclick="openAppointmentDetailsModal(this)"
                        data-id="{{ $appointment->id }}"
                        data-time="{{ optional($appointment->scheduled_at)->format('g:i A') }} - {{ optional($appointment->ends_at)->format('g:i A') ?: 'TBD' }}"
                        data-customer-name="{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}"
                        data-customer-email="{{ $appointment->customer?->email ?: '-' }}"
                        data-customer-phone="{{ $appointment->customer?->phone ?: '-' }}"
                        data-customer-membership="{{ $membershipName ?: 'No active membership' }}"
                        data-services="{{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services selected' }}"
                        data-arrived="{{ $appointment->arrived_confirmed ? '1' : '0' }}"
                        data-arrival-action="{{ route('appointments.arrival.update', $appointment) }}"
                        data-staff-user-id="{{ $appointment->staff_user_id ?? '' }}"
                        data-staff-name="{{ $appointment->staffUser?->name ?: 'Unassigned' }}"
                        data-staff-action="{{ route('appointments.staff.update', $appointment) }}"
                    >
                        <p class="font-medium">{{ optional($appointment->scheduled_at)->format('g:i A') }} - {{ optional($appointment->ends_at)->format('g:i A') ?: 'TBD' }}</p>
                        <p class="text-sm">{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}</p>
                        <p class="text-xs text-slate-500">Staff: {{ $appointment->staffUser?->name ?: 'Unassigned' }}</p>
                        <p class="text-xs text-slate-500">
                            Services: {{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services selected' }}
                        </p>
                    </button>
                @empty
                    <p class="text-sm text-slate-500">No appointments on this day.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div id="appointmentDetailsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Appointment Details</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeAppointmentDetailsModal()">✕</button>
            </div>
            <div class="space-y-2 text-sm">
                <p><span class="font-semibold">Time:</span> <span id="modal_appt_time"></span></p>
                <p><span class="font-semibold">Customer:</span> <span id="modal_customer_name"></span></p>
                <p><span class="font-semibold">Email:</span> <span id="modal_customer_email"></span></p>
                <p><span class="font-semibold">Phone:</span> <span id="modal_customer_phone"></span></p>
                <p><span class="font-semibold">Membership:</span> <span id="modal_customer_membership"></span></p>
                <p><span class="font-semibold">Services:</span> <span id="modal_services"></span></p>
                <p><span class="font-semibold">Staff:</span> <span id="modal_staff_name"></span></p>
                <p><span class="font-semibold">Current Time:</span> <span id="modal_current_time"></span></p>
                <p>
                    <span class="font-semibold">Arrival Confirmed:</span>
                    <span id="modal_arrival_state" class="ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"></span>
                </p>
            </div>
            <form id="arrivalConfirmForm" method="POST" action="" class="mt-4">
                @csrf
                @method('PATCH')
                <input id="modal_arrived_input" type="hidden" name="arrived_confirmed" value="0">
                <button id="arrivalConfirmBtn" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Confirm arrival
                </button>
            </form>
            <form id="staffAssignForm" method="POST" action="" class="mt-4 border-t border-slate-200 pt-4">
                @csrf
                @method('PATCH')
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Assign staff</label>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select id="modal_staff_select" name="staff_user_id" class="w-full flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm sm:max-w-xs">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">
                        Save staff
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="addAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeAddAppointmentModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('appointments.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select name="customer_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select name="staff_user_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Start time</label>
                        <input name="scheduled_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">End time</label>
                        <input name="ends_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium">Services</label>
                        <button type="button" class="text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addServiceRow()">+ Add service</button>
                    </div>
                    <div id="servicesContainer" class="space-y-2"></div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeAddAppointmentModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const addModal = document.getElementById('addAppointmentModal');
        const detailsModal = document.getElementById('appointmentDetailsModal');
        const servicesContainer = document.getElementById('servicesContainer');
        const serviceOptions = @json($serviceOptions);
        let modalClockInterval = null;

        function openAddAppointmentModal() {
            servicesContainer.innerHTML = '';
            addServiceRow();
            addModal.classList.remove('hidden');
            addModal.classList.add('flex');
        }

        function closeAddAppointmentModal() {
            addModal.classList.add('hidden');
            addModal.classList.remove('flex');
        }

        function openAppointmentDetailsModal(button) {
            document.getElementById('modal_appt_time').textContent = button.dataset.time || '-';
            document.getElementById('modal_customer_name').textContent = button.dataset.customerName || '-';
            document.getElementById('modal_customer_email').textContent = button.dataset.customerEmail || '-';
            document.getElementById('modal_customer_phone').textContent = button.dataset.customerPhone || '-';
            document.getElementById('modal_customer_membership').textContent = button.dataset.customerMembership || '-';
            document.getElementById('modal_services').textContent = button.dataset.services || '-';
            document.getElementById('modal_staff_name').textContent = button.dataset.staffName || 'Unassigned';

            const staffForm = document.getElementById('staffAssignForm');
            const staffSelect = document.getElementById('modal_staff_select');
            staffForm.action = button.dataset.staffAction || '';
            staffSelect.value = button.dataset.staffUserId || '';

            const arrived = button.dataset.arrived === '1';
            const stateBadge = document.getElementById('modal_arrival_state');
            stateBadge.textContent = arrived ? 'Yes' : 'No';
            stateBadge.className = `ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${arrived ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`;

            const arrivalForm = document.getElementById('arrivalConfirmForm');
            const arrivalInput = document.getElementById('modal_arrived_input');
            const arrivalBtn = document.getElementById('arrivalConfirmBtn');
            arrivalForm.action = button.dataset.arrivalAction || '';
            arrivalInput.value = arrived ? '0' : '1';
            arrivalBtn.textContent = arrived ? 'Mark not arrived' : 'Confirm arrival';
            arrivalBtn.className = `rounded-md px-4 py-2 text-sm font-semibold text-white ${arrived ? 'bg-slate-700 hover:bg-slate-800' : 'bg-blue-600 hover:bg-blue-700'}`;

            updateModalCurrentTime();
            if (modalClockInterval) clearInterval(modalClockInterval);
            modalClockInterval = setInterval(updateModalCurrentTime, 1000);

            detailsModal.classList.remove('hidden');
            detailsModal.classList.add('flex');
        }

        function closeAppointmentDetailsModal() {
            detailsModal.classList.add('hidden');
            detailsModal.classList.remove('flex');
            if (modalClockInterval) {
                clearInterval(modalClockInterval);
                modalClockInterval = null;
            }
        }

        function updateModalCurrentTime() {
            const now = new Date();
            document.getElementById('modal_current_time').textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        }

        function addServiceRow() {
            const index = servicesContainer.querySelectorAll('[data-service-row]').length;
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-service-row', 'true');
            wrapper.className = 'grid gap-2 md:grid-cols-[1fr_120px_80px]';
            const options = serviceOptions
                .map((service) => `<option value="${service.id}">${service.name} ($${service.price.toFixed(2)})</option>`)
                .join('');

            wrapper.innerHTML = `
                <select name="services[${index}][service_id]" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Select service</option>
                    ${options}
                </select>
                <input name="services[${index}][quantity]" type="number" min="1" value="1" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Remove</button>
            `;
            wrapper.querySelector('button').addEventListener('click', () => wrapper.remove());
            servicesContainer.appendChild(wrapper);
        }
    </script>
@endsection
