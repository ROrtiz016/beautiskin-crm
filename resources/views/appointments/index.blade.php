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
    if (!empty($filters['search'])) {
        $activeChips[] = ['key' => 'search', 'label' => 'Search: ' . $filters['search']];
    }
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Appointments</h1>
            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">Use the calendar to pick a day, review the list on the right, and drag appointments to reschedule when needed.</p>
        </div>
        <button type="button" class="crm-btn-primary shrink-0" onclick="openAddAppointmentModal()">
            + New appointment
        </button>
    </div>

    @if ($clinicSettings->appointment_cancellation_hours > 0 || $clinicSettings->max_bookings_per_day || $clinicSettings->deposit_required)
        <div class="mb-4 rounded-xl border border-slate-300 bg-white/90 px-4 py-3 text-xs leading-relaxed text-slate-700 shadow-sm">
            <span class="font-semibold text-slate-900">Clinic policy</span>
            <span class="text-slate-500"> — </span>
            @if ($clinicSettings->appointment_cancellation_hours > 0)
                Cancellations require {{ $clinicSettings->appointment_cancellation_hours }}+ hour(s) notice before start.
            @endif
            @if ($clinicSettings->max_bookings_per_day)
                Max {{ $clinicSettings->max_bookings_per_day }} active booking(s) per day.
            @endif
            @if ($clinicSettings->deposit_required)
                New appointments require a recorded deposit.
                @if ($clinicSettings->default_deposit_amount)
                    Default deposit: ${{ number_format((float) $clinicSettings->default_deposit_amount, 2) }}.
                @endif
            @endif
        </div>
    @endif

    @can('view-experimental-ui')
        <section class="mb-6 rounded-xl border border-violet-200 bg-violet-50/80 p-4">
            <h2 class="text-sm font-semibold text-violet-900">Experimental — schedule density</h2>
            <p class="mt-1 text-xs text-violet-800">
                Calendar day <span class="font-mono">{{ $selectedDate->toDateString() }}</span> has
                <span class="font-semibold">{{ $selectedAppointments->count() }}</span> listed slot(s) in this view.
                Open <a href="{{ route('admin.operations.index') }}" class="font-semibold underline">Operations</a> for revenue, waitlist, and utilization.
            </p>
        </section>
    @endcan

    <section class="mb-6 crm-panel p-5">
        <div class="mb-4 border-b border-slate-200 pb-4">
            <h2 class="text-base font-semibold text-slate-900">Filters</h2>
            <p class="mt-0.5 text-xs text-slate-500">Narrow the calendar and lists. Month and selected date are kept when you apply.</p>
        </div>
        <form method="GET" action="{{ route('appointments.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-8">
            <input type="hidden" id="filterMonthInput" name="month" value="{{ $monthBase->format('Y-m') }}">
            <input type="hidden" id="filterDateInput" name="date" value="{{ $selectedDate->toDateString() }}">
            <div class="2xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</label>
                <select name="customer_id" class="crm-input">
                    <option value="">All customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (int) $filters['customer_id'] === $customer->id ? 'selected' : '' }}>
                            {{ $customer->first_name }} {{ $customer->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="2xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Phone / Email Search</label>
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Search customer phone or email"
                    class="crm-input"
                >
            </div>
            <div class="2xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Service</label>
                <select name="service_id" class="crm-input">
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
                <select name="status" class="crm-input">
                    <option value="">All statuses</option>
                    @foreach (['booked', 'completed', 'cancelled', 'no_show'] as $status)
                        <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Arrived</label>
                <select name="arrived" class="crm-input">
                    <option value="">All</option>
                    <option value="yes" {{ $filters['arrived'] === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no" {{ $filters['arrived'] === 'no' ? 'selected' : '' }}>No</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</label>
                <select name="staff_user_id" class="crm-input">
                    <option value="">All staff</option>
                    @foreach ($staffUsers as $staff)
                        <option value="{{ $staff->id }}" {{ (int) $filters['staff_user_id'] === $staff->id ? 'selected' : '' }}>
                            {{ $staff->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-3 2xl:col-span-8 flex items-center gap-2">
                <button type="submit" class="crm-btn-primary">Apply filters</button>
                <a href="{{ route('appointments.index', ['month' => $monthBase->format('Y-m'), 'date' => $selectedDate->toDateString()]) }}" class="crm-btn-secondary text-sm">Clear filters</a>
            </div>
        </form>
    </section>

    @php
        $calendarJsConfig = [
            'day_fragment_url' => route('appointments.day'),
            'index_url' => route('appointments.index'),
            'csrf_token' => csrf_token(),
            'month' => $monthBase->format('Y-m'),
            'selected_date' => $selectedDate->toDateString(),
            'filters' => $filters,
        ];
    @endphp
    <script type="application/json" id="appointmentsCalendarConfig">{!! json_encode($calendarJsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    @if (count($activeChips) > 0)
        <section class="mb-6 crm-panel p-4">
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

    <section class="mb-6 crm-panel p-5">
        <h2 class="text-lg font-semibold">Today's Appointments ({{ $today->format('Y-m-d') }})</h2>
        <div class="mt-4 space-y-3">
            @forelse ($todaysAppointments as $appointment)
                <div class="rounded-lg border border-slate-300/90 bg-slate-50/50 px-3 py-2 shadow-sm">
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
        <section class="crm-panel p-5 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <a class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-100" href="{{ route('appointments.index', array_merge($filters, ['month' => $prevMonth, 'date' => $selectedDate->copy()->subMonth()->toDateString()])) }}">
                    ← Previous
                </a>
                <h2 class="text-lg font-semibold">{{ $monthBase->format('F Y') }}</h2>
                <a class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-100" href="{{ route('appointments.index', array_merge($filters, ['month' => $nextMonth, 'date' => $selectedDate->copy()->addMonth()->toDateString()])) }}">
                    Next →
                </a>
            </div>

            <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>
            <p class="mt-2 text-xs text-slate-500">Tip: drag an appointment from the selected-day panel onto another day to reschedule it.</p>

            <div class="mt-2 space-y-2" id="appointmentsCalendarGrid">
                @foreach ($weeks as $week)
                    <div class="grid grid-cols-7 gap-2">
                        @foreach ($week as $day)
                            <button
                                type="button"
                                data-date="{{ $day['date']->toDateString() }}"
                                class="js-calendar-day min-h-[74px] rounded-lg border px-2 py-2 text-left transition
                                    {{ $day['in_month'] ? 'border-slate-300 bg-white shadow-sm hover:border-pink-400 hover:shadow-md' : 'border-slate-200/80 bg-slate-100/80 text-slate-500' }}
                                    {{ $day['is_selected'] ? 'ring-2 ring-pink-500 ring-offset-2 ring-offset-slate-100' : '' }}
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold {{ $day['is_today'] ? 'text-pink-700' : '' }}">{{ $day['date']->day }}</span>
                                    @if ($day['count'] > 0)
                                        <span class="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ $day['count'] }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <section class="crm-panel p-5" id="selectedDaySection">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Selected Day</h2>
                <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-100" onclick="openWaitlistModal()">
                    + Add to Waitlist
                </button>
            </div>
            <div id="selectedDayPanel">
                @include('appointments.partials.selected-day-panel', ['selectedDate' => $selectedDate, 'selectedAppointments' => $selectedAppointments, 'selectedWaitlistEntries' => $selectedWaitlistEntries, 'staffAvailability' => $staffAvailability])
            </div>
        </section>
    </div>

    <div id="appointmentDetailsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
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
                <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Billing &amp; deposits</p>
                    <p class="mt-2 text-slate-800">
                        Visit total <span id="modal_visit_total" class="font-semibold">$0.00</span>
                        · Payments <span id="modal_payments_applied" class="font-semibold">$0.00</span>
                        · Balance due <span id="modal_balance_due" class="font-semibold text-amber-800">$0.00</span>
                    </p>
                    <div id="modal_payment_entries" class="mt-2 max-h-36 space-y-1 overflow-y-auto text-xs text-slate-600"></div>
                    <form id="modal_payment_entry_form" method="POST" action="" class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="mb-0.5 block text-[11px] font-semibold text-slate-500">Type</label>
                            <select name="entry_type" class="crm-input text-sm" required>
                                <option value="deposit">Deposit</option>
                                <option value="payment">Payment</option>
                                <option value="refund">Refund (records as negative)</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[11px] font-semibold text-slate-500">Amount</label>
                            <input name="amount" type="number" step="0.01" min="0.01" class="crm-input text-sm" required>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[11px] font-semibold text-slate-500">Note (optional)</label>
                            <input name="note" type="text" maxlength="500" class="crm-input text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="crm-btn-primary w-full text-sm py-2">Record entry</button>
                        </div>
                    </form>
                </div>
                <div id="modal_cancellation_wrap" class="hidden rounded-md border border-rose-200 bg-rose-50/80 px-3 py-2 text-xs text-rose-950">
                    <p class="font-semibold text-rose-900">Cancellation</p>
                    <p id="modal_cancellation_reason" class="mt-1 whitespace-pre-wrap"></p>
                    <p id="modal_cancellation_meta" class="mt-1 text-rose-800/90"></p>
                </div>
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
                    <select id="modal_staff_select" name="staff_user_id" class="crm-input flex-1 sm:max-w-xs">
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
            <div id="modal_email_reminder_section" class="mt-4 border-t border-slate-200 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email reminder</p>
                <div id="modal_email_reminder_status" class="mt-2 hidden flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-sm text-emerald-950">
                    <span class="inline-flex size-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    <span><span class="font-semibold">Reminder sent</span> <span id="modal_email_reminder_when" class="text-emerald-900/90"></span></span>
                </div>
                <p id="modal_email_reminder_no_email" class="mt-2 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    This customer has no email on file. Add an email on the customer profile before sending a reminder.
                </p>
                <form id="emailReminderForm" method="POST" action="" class="mt-3">
                    @csrf
                    <button type="submit" id="emailReminderSubmitBtn" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Send email reminder
                    </button>
                    <p id="modal_email_reminder_hint" class="mt-2 text-xs text-slate-500">Uses your clinic reminder template. Re-send sends another email and updates the sent time.</p>
                </form>
            </div>
        </div>
    </div>

    <div id="addAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeAddAppointmentModal()">✕</button>
            </div>
            <form id="addAppointmentForm" method="POST" action="{{ route('appointments.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="store">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select name="customer_id" class="crm-input" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) old('customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->first_name }} {{ $customer->last_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select name="staff_user_id" class="crm-input">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}" {{ (string) old('staff_user_id') === (string) $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @error('staff_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Start time</label>
                        <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="crm-input" required>
                        @error('scheduled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">End time</label>
                        <input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="crm-input">
                        @error('ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium">Services</label>
                        <button type="button" class="text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addServiceRow(document.getElementById('servicesContainer'))">+ Add service</button>
                    </div>
                    <div id="servicesContainer" class="space-y-2"></div>
                    @error('services') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('services.*.service_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('services.*.quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @if ($clinicSettings->deposit_required)
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3">
                        <label class="flex items-start gap-2 text-sm text-amber-950">
                            <input type="checkbox" name="deposit_paid" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('form_type') === 'store' && old('deposit_paid'))>
                            <span>
                                Deposit collected (required for new bookings)
                                @if ($clinicSettings->default_deposit_amount)
                                    — default <span class="font-semibold">${{ number_format((float) $clinicSettings->default_deposit_amount, 2) }}</span>
                                @endif
                            </span>
                        </label>
                        @error('deposit_paid') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeAddAppointmentModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditAppointmentModal()">✕</button>
            </div>
            <form id="editAppointmentForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_type" value="update">
                <input type="hidden" id="edit_appointment_id" name="appointment_id" value="{{ old('appointment_id') }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select id="edit_customer_id" name="customer_id" class="crm-input" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) old('customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->first_name }} {{ $customer->last_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select id="edit_staff_user_id" name="staff_user_id" class="crm-input">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $staff)
                            <option value="{{ $staff->id }}" {{ (string) old('staff_user_id') === (string) $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @error('staff_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Start time</label>
                        <input id="edit_scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="crm-input" required>
                        @error('scheduled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">End time</label>
                        <input id="edit_ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="crm-input">
                        @error('ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium">Services</label>
                        <button type="button" class="text-xs font-semibold text-pink-700 hover:text-pink-800" onclick="addServiceRow(document.getElementById('editServicesContainer'))">+ Add service</button>
                    </div>
                    <div id="editServicesContainer" class="space-y-2"></div>
                    @error('services') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('services.*.service_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('services.*.quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @if (old('form_type') === 'update')
                    <p class="text-xs text-red-600">Please review the highlighted appointment fields and try again.</p>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeEditAppointmentModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="cancelAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-md">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Cancel appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCancelAppointmentModal()">✕</button>
            </div>
            <p class="text-sm text-slate-600">
                <span id="cancel_modal_customer_label" class="font-medium text-slate-800"></span>
            </p>
            <form id="cancelAppointmentForm" method="POST" action="" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_type" value="cancel">
                <input type="hidden" name="status" value="cancelled">
                <input type="hidden" name="cancel_appointment_action" id="cancel_appointment_action_field" value="{{ old('cancel_appointment_action') }}">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Cancellation reason <span class="text-rose-600">*</span></label>
                    <textarea name="cancellation_reason" rows="4" class="crm-input" required placeholder="Why is this visit being cancelled?">{{ old('cancellation_reason') }}</textarea>
                    @error('cancellation_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <input type="hidden" name="sales_follow_up_needed" value="0">
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="checkbox" name="sales_follow_up_needed" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('sales_follow_up_needed') == '1' || old('sales_follow_up_needed') === true || old('sales_follow_up_needed') === 1)>
                        <span>Sales team should follow up with this customer</span>
                    </label>
                    @error('sales_follow_up_needed') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-slate-500">Your account will be recorded as the staff member who processed this cancellation.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeCancelAppointmentModal()">Close</button>
                    <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <div id="waitlistModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add to Waitlist</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeWaitlistModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('appointments.waitlist.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="waitlist">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select name="customer_id" class="crm-input" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) old('customer_id') === (string) $customer->id ? 'selected' : '' }}>
                                {{ $customer->first_name }} {{ $customer->last_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Preferred date</label>
                        <input id="waitlist_preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date', $selectedDate->format('Y-m-d')) }}" class="crm-input" required>
                        @error('preferred_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Preferred staff (optional)</label>
                        <select name="staff_user_id" class="crm-input">
                            <option value="">Any staff</option>
                            @foreach ($staffUsers as $staff)
                                <option value="{{ $staff->id }}" {{ (string) old('staff_user_id') === (string) $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        @error('staff_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Preferred service (optional)</label>
                    <select name="service_id" class="crm-input">
                        <option value="">Any service</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" {{ (string) old('service_id') === (string) $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('service_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Lead source</label>
                    <select name="lead_source" class="crm-input">
                        @foreach (collect($leadSourceOptions)->groupBy('group') as $groupName => $opts)
                            <optgroup label="{{ $groupName }}">
                                @foreach ($opts as $opt)
                                    <option value="{{ $opt['value'] }}" @selected(old('lead_source', 'unknown') === $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        <option value="unknown" @selected(old('lead_source', 'unknown') === 'unknown')>{{ \App\Support\LeadSource::label('unknown') }}</option>
                    </select>
                    @error('lead_source') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Preferred start time</label>
                        <input name="preferred_start_time" type="time" value="{{ old('preferred_start_time') }}" class="crm-input">
                        @error('preferred_start_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Preferred end time</label>
                        <input name="preferred_end_time" type="time" value="{{ old('preferred_end_time') }}" class="crm-input">
                        @error('preferred_end_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm" onclick="closeWaitlistModal()">Cancel</button>
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Save waitlist entry</button>
                </div>
            </form>
        </div>
    </div>

    @include('waitlist.partials.mark-contacted-modal')

    <script>
        const addModal = document.getElementById('addAppointmentModal');
        const editAppointmentModal = document.getElementById('editAppointmentModal');
        const waitlistModal = document.getElementById('waitlistModal');
        const editAppointmentForm = document.getElementById('editAppointmentForm');
        const detailsModal = document.getElementById('appointmentDetailsModal');
        const servicesContainer = document.getElementById('servicesContainer');
        const editServicesContainer = document.getElementById('editServicesContainer');
        const serviceOptions = @json($serviceOptions);
        const oldServiceLines = @json(old('services', []));

        const calendarConfig = JSON.parse(document.getElementById('appointmentsCalendarConfig').textContent);
        const selectedDayPanel = document.getElementById('selectedDayPanel');
        const filterDateInput = document.getElementById('filterDateInput');
        let selectedDayLoading = false;
        let draggedAppointment = null;

        function appointmentsQueryParams(dateStr) {
            const params = new URLSearchParams();
            params.set('month', calendarConfig.month);
            params.set('date', dateStr);
            const f = calendarConfig.filters || {};
            if (f.status) params.set('status', f.status);
            if (f.customer_id) params.set('customer_id', String(f.customer_id));
            if (f.search) params.set('search', f.search);
            if (f.service_id) params.set('service_id', String(f.service_id));
            if (f.arrived) params.set('arrived', f.arrived);
            if (f.staff_user_id) params.set('staff_user_id', String(f.staff_user_id));
            return params;
        }

        function syncCalendarSelectionRing(dateStr) {
            document.querySelectorAll('.js-calendar-day').forEach((el) => {
                const on = el.dataset.date === dateStr;
                el.classList.toggle('ring-2', on);
                el.classList.toggle('ring-pink-500', on);
            });
        }

        async function loadSelectedDay(dateStr, options = {}) {
            const { pushState = false } = options;
            if (selectedDayLoading) return;
            selectedDayLoading = true;
            selectedDayPanel.classList.add('opacity-50', 'pointer-events-none');
            try {
                const params = appointmentsQueryParams(dateStr);
                const res = await fetch(`${calendarConfig.day_fragment_url}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                selectedDayPanel.innerHTML = data.html;
                calendarConfig.selected_date = data.date;
                if (data.month) {
                    calendarConfig.month = data.month;
                }
                if (filterDateInput) filterDateInput.value = data.date;
                const waitlistDateInput = document.getElementById('waitlist_preferred_date');
                if (waitlistDateInput) waitlistDateInput.value = data.date;
                const monthInput = document.getElementById('filterMonthInput');
                if (monthInput && data.month) monthInput.value = data.month;
                syncCalendarSelectionRing(data.date);
                if (pushState) {
                    const url = `${calendarConfig.index_url}?${params.toString()}`;
                    window.history.pushState({ appointmentsDate: data.date }, '', url);
                }
            } catch {
                window.location.href = `${calendarConfig.index_url}?${appointmentsQueryParams(dateStr).toString()}`;
            } finally {
                selectedDayPanel.classList.remove('opacity-50', 'pointer-events-none');
                selectedDayLoading = false;
            }
        }

        document.getElementById('appointmentsCalendarGrid')?.addEventListener('click', (event) => {
            const btn = event.target.closest('.js-calendar-day');
            if (!btn || selectedDayLoading) return;
            const dateStr = btn.dataset.date;
            if (!dateStr || dateStr === calendarConfig.selected_date) return;
            loadSelectedDay(dateStr, { pushState: true });
        });

        document.getElementById('appointmentsCalendarGrid')?.addEventListener('dragover', (event) => {
            const btn = event.target.closest('.js-calendar-day');
            if (!btn || !draggedAppointment) return;
            event.preventDefault();
            btn.classList.add('border-pink-500', 'bg-pink-50');
        });

        document.getElementById('appointmentsCalendarGrid')?.addEventListener('dragleave', (event) => {
            const btn = event.target.closest('.js-calendar-day');
            if (!btn) return;
            btn.classList.remove('border-pink-500', 'bg-pink-50');
        });

        document.getElementById('appointmentsCalendarGrid')?.addEventListener('drop', async (event) => {
            const btn = event.target.closest('.js-calendar-day');
            if (!btn || !draggedAppointment) return;
            event.preventDefault();
            btn.classList.remove('border-pink-500', 'bg-pink-50');
            const targetDate = btn.dataset.date;
            if (!targetDate || targetDate === draggedAppointment.currentDate) {
                draggedAppointment = null;
                return;
            }

            try {
                const res = await fetch(draggedAppointment.action, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': calendarConfig.csrf_token,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ target_date: targetDate }),
                });

                if (!res.ok) {
                    throw new Error('reschedule failed');
                }

                if (targetDate === calendarConfig.selected_date) {
                    await loadSelectedDay(targetDate, { pushState: false });
                } else {
                    window.location.href = `${calendarConfig.index_url}?${appointmentsQueryParams(targetDate).toString()}`;
                    return;
                }
            } catch {
                window.location.href = `${calendarConfig.index_url}?${appointmentsQueryParams(targetDate).toString()}`;
                return;
            } finally {
                draggedAppointment = null;
            }
        });

        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(window.location.search);
            const urlMonth = params.get('month') || calendarConfig.month;
            if (urlMonth !== calendarConfig.month) {
                window.location.reload();
                return;
            }
            const dateStr = params.get('date') || calendarConfig.selected_date;
            const f = calendarConfig.filters;
            f.status = params.get('status') || '';
            f.customer_id = parseInt(params.get('customer_id') || '0', 10) || 0;
            f.search = params.get('search') || '';
            f.service_id = parseInt(params.get('service_id') || '0', 10) || 0;
            f.arrived = params.get('arrived') || '';
            f.staff_user_id = parseInt(params.get('staff_user_id') || '0', 10) || 0;
            const monthInput = document.getElementById('filterMonthInput');
            if (monthInput) monthInput.value = calendarConfig.month;
            loadSelectedDay(dateStr, { pushState: false });
        });

        function openAddAppointmentModal() {
            servicesContainer.innerHTML = '';
            addServiceRow(servicesContainer);
            addModal.classList.remove('hidden');
            addModal.classList.add('flex');
        }

        function closeAddAppointmentModal() {
            addModal.classList.add('hidden');
            addModal.classList.remove('flex');
        }

        function openEditAppointmentModal(button) {
            editAppointmentForm.action = button.dataset.updateAction || '';
            document.getElementById('edit_appointment_id').value = button.dataset.id || '';
            document.getElementById('edit_customer_id').value = button.dataset.customerId || '';
            document.getElementById('edit_staff_user_id').value = button.dataset.staffUserId || '';
            document.getElementById('edit_scheduled_at').value = button.dataset.scheduledAt || '';
            document.getElementById('edit_ends_at').value = button.dataset.endsAt || '';
            document.getElementById('edit_notes').value = button.dataset.notes || '';

            editServicesContainer.innerHTML = '';
            const serviceLines = parseServicesJson(button.dataset.servicesJson);
            if (serviceLines.length > 0) {
                serviceLines.forEach((line) => addServiceRow(editServicesContainer, line.service_id, line.quantity));
            } else {
                addServiceRow(editServicesContainer);
            }

            editAppointmentModal.classList.remove('hidden');
            editAppointmentModal.classList.add('flex');
        }

        function closeEditAppointmentModal() {
            editAppointmentModal.classList.add('hidden');
            editAppointmentModal.classList.remove('flex');
        }

        const cancelAppointmentModal = document.getElementById('cancelAppointmentModal');

        function openCancelAppointmentModal(actionUrl, customerLabel = '') {
            if (!actionUrl || !cancelAppointmentModal) return;
            const form = document.getElementById('cancelAppointmentForm');
            form.action = actionUrl;
            const actionField = document.getElementById('cancel_appointment_action_field');
            if (actionField) {
                actionField.value = actionUrl;
            }
            const lbl = document.getElementById('cancel_modal_customer_label');
            if (lbl) {
                lbl.textContent = customerLabel ? `Customer: ${customerLabel}` : '';
            }
            cancelAppointmentModal.classList.remove('hidden');
            cancelAppointmentModal.classList.add('flex');
        }

        function closeCancelAppointmentModal() {
            if (!cancelAppointmentModal) return;
            cancelAppointmentModal.classList.add('hidden');
            cancelAppointmentModal.classList.remove('flex');
        }

        function openWaitlistModal(dateStr = '') {
            const input = document.getElementById('waitlist_preferred_date');
            if (input) {
                input.value = dateStr || calendarConfig.selected_date || input.value;
            }
            waitlistModal.classList.remove('hidden');
            waitlistModal.classList.add('flex');
        }

        function closeWaitlistModal() {
            waitlistModal.classList.add('hidden');
            waitlistModal.classList.remove('flex');
        }

        function openAppointmentDetailsModal(button) {
            document.getElementById('modal_appt_time').textContent = button.dataset.time || '-';
            document.getElementById('modal_customer_name').textContent = button.dataset.customerName || '-';
            document.getElementById('modal_customer_email').textContent = button.dataset.customerEmail || '-';
            document.getElementById('modal_customer_phone').textContent = button.dataset.customerPhone || '-';
            document.getElementById('modal_customer_membership').textContent = button.dataset.customerMembership || '-';
            document.getElementById('modal_services').textContent = button.dataset.services || '-';
            document.getElementById('modal_staff_name').textContent = button.dataset.staffName || 'Unassigned';

            const visitTotal = button.dataset.visitTotal || '0.00';
            const paymentsApplied = button.dataset.paymentsApplied || '0.00';
            const balanceDue = button.dataset.balanceDue || '0.00';
            document.getElementById('modal_visit_total').textContent = '$' + visitTotal;
            document.getElementById('modal_payments_applied').textContent = '$' + paymentsApplied;
            const balEl = document.getElementById('modal_balance_due');
            balEl.textContent = '$' + balanceDue;
            balEl.className = 'font-semibold ' + (parseFloat(balanceDue) > 0 ? 'text-amber-800' : 'text-emerald-800');

            const entriesEl = document.getElementById('modal_payment_entries');
            entriesEl.innerHTML = '';
            let entries = [];
            try {
                entries = JSON.parse(button.getAttribute('data-payment-entries') || '[]');
            } catch {
                entries = [];
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            if (!Array.isArray(entries) || entries.length === 0) {
                entriesEl.innerHTML = '<p class="text-slate-400">No payment entries yet.</p>';
            } else {
                entries.forEach((row) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-1';
                    const amt = Number(row.amount);
                    const span = document.createElement('span');
                    span.textContent = (row.entry_type || '') + ': $' + amt.toFixed(2) + (row.note ? ' — ' + row.note : '');
                    const del = document.createElement('form');
                    del.method = 'POST';
                    del.action = '/appointment-payment-entries/' + row.id;
                    del.className = 'shrink-0';
                    del.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">' +
                        '<input type="hidden" name="_method" value="DELETE">' +
                        '<button type="submit" class="text-[11px] text-red-600 hover:text-red-800">Remove</button>';
                    rowEl.appendChild(span);
                    rowEl.appendChild(del);
                    entriesEl.appendChild(rowEl);
                });
            }
            const payForm = document.getElementById('modal_payment_entry_form');
            payForm.action = button.dataset.paymentEntryStore || '';
            payForm.reset();
            const tokenInput = payForm.querySelector('input[name="_token"]');
            if (tokenInput) {
                tokenInput.value = csrf;
            }

            const staffForm = document.getElementById('staffAssignForm');
            const staffSelect = document.getElementById('modal_staff_select');
            staffForm.action = button.dataset.staffAction || '';
            staffSelect.value = button.dataset.staffUserId || '';
            document.getElementById('emailReminderForm').action = button.dataset.reminderAction || '';

            const customerEmail = (button.dataset.customerEmail || '').trim();
            const hasCustomerEmail = customerEmail !== '' && customerEmail !== '-';
            const reminderSent = (button.dataset.emailReminderSent || '') === '1';
            const reminderLabel = button.dataset.emailReminderLabel || '';
            const noEmailEl = document.getElementById('modal_email_reminder_no_email');
            const statusEl = document.getElementById('modal_email_reminder_status');
            const whenEl = document.getElementById('modal_email_reminder_when');
            const reminderForm = document.getElementById('emailReminderForm');
            const reminderBtn = document.getElementById('emailReminderSubmitBtn');
            const reminderHint = document.getElementById('modal_email_reminder_hint');

            if (!hasCustomerEmail) {
                noEmailEl.classList.remove('hidden');
                statusEl.classList.add('hidden');
                reminderForm.classList.add('hidden');
                if (reminderHint) {
                    reminderHint.classList.add('hidden');
                }
            } else {
                noEmailEl.classList.add('hidden');
                reminderForm.classList.remove('hidden');
                if (reminderHint) {
                    reminderHint.classList.remove('hidden');
                }
                if (reminderSent && reminderLabel) {
                    statusEl.classList.remove('hidden');
                    whenEl.textContent = 'on ' + reminderLabel + ' (clinic time)';
                } else if (reminderSent) {
                    statusEl.classList.remove('hidden');
                    whenEl.textContent = '(send time not recorded)';
                } else {
                    statusEl.classList.add('hidden');
                }
                reminderBtn.textContent = reminderSent ? 'Re-send email reminder' : 'Send email reminder';
            }

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

            const cancelWrap = document.getElementById('modal_cancellation_wrap');
            const cancelRaw = button.getAttribute('data-cancellation');
            if (cancelWrap) {
                if (cancelRaw) {
                    try {
                        const c = JSON.parse(cancelRaw);
                        cancelWrap.classList.remove('hidden');
                        document.getElementById('modal_cancellation_reason').textContent = c.reason || 'No reason on file.';
                        const parts = [];
                        if (c.cancelled_by) parts.push(`Logged by ${c.cancelled_by}`);
                        if (c.cancelled_at) parts.push(c.cancelled_at);
                        if (c.sales_follow_up) parts.push('Sales follow-up requested');
                        document.getElementById('modal_cancellation_meta').textContent = parts.join(' · ');
                    } catch {
                        cancelWrap.classList.add('hidden');
                    }
                } else {
                    cancelWrap.classList.add('hidden');
                }
            }

            detailsModal.classList.remove('hidden');
            detailsModal.classList.add('flex');
        }

        function closeAppointmentDetailsModal() {
            detailsModal.classList.add('hidden');
            detailsModal.classList.remove('flex');
        }

        function parseServicesJson(raw) {
            if (!raw) return [];
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        }

        function addServiceRow(container, selectedServiceId = '', quantity = 1) {
            const index = container.querySelectorAll('[data-service-row]').length;
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-service-row', 'true');
            wrapper.className = 'grid gap-2 md:grid-cols-[1fr_120px_80px]';
            const options = serviceOptions
                .map((service) => {
                    const selected = String(selectedServiceId) === String(service.id) ? 'selected' : '';
                    return `<option value="${service.id}" ${selected}>${service.name} ($${service.price.toFixed(2)})</option>`;
                })
                .join('');

            wrapper.innerHTML = `
                <select name="services[${index}][service_id]" class="crm-input" required>
                    <option value="">Select service</option>
                    ${options}
                </select>
                <input name="services[${index}][quantity]" type="number" min="1" value="${quantity || 1}" class="crm-input max-w-[6rem]">
                <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-100">Remove</button>
            `;
            wrapper.querySelector('button').addEventListener('click', () => wrapper.remove());
            container.appendChild(wrapper);
        }

        document.addEventListener('dragstart', (event) => {
            const card = event.target.closest('.js-draggable-appointment');
            if (!card) return;
            draggedAppointment = {
                id: card.dataset.id,
                action: card.dataset.rescheduleAction,
                currentDate: (card.dataset.scheduledAt || '').slice(0, 10),
            };
            event.dataTransfer.effectAllowed = 'move';
        });

        document.addEventListener('dragend', () => {
            draggedAppointment = null;
            document.querySelectorAll('.js-calendar-day').forEach((el) => {
                el.classList.remove('border-pink-500', 'bg-pink-50');
            });
        });

        @if ($errors->any() && old('form_type') === 'store')
            servicesContainer.innerHTML = '';
            if (Array.isArray(oldServiceLines) && oldServiceLines.length > 0) {
                oldServiceLines.forEach((line) => addServiceRow(servicesContainer, line.service_id || '', line.quantity || 1));
            } else {
                addServiceRow(servicesContainer);
            }
            addModal.classList.remove('hidden');
            addModal.classList.add('flex');
        @endif

        @if ($errors->any() && old('form_type') === 'update' && old('appointment_id'))
            editAppointmentForm.action = `/appointments/{{ old('appointment_id') }}`;
            editServicesContainer.innerHTML = '';
            if (Array.isArray(oldServiceLines) && oldServiceLines.length > 0) {
                oldServiceLines.forEach((line) => addServiceRow(editServicesContainer, line.service_id || '', line.quantity || 1));
            } else {
                addServiceRow(editServicesContainer);
            }
            editAppointmentModal.classList.remove('hidden');
            editAppointmentModal.classList.add('flex');
        @endif

        @if ($errors->any() && old('form_type') === 'waitlist')
            waitlistModal.classList.remove('hidden');
            waitlistModal.classList.add('flex');
        @endif

        @if ($errors->any() && old('form_type') === 'cancel')
            openCancelAppointmentModal(@json(old('cancel_appointment_action', '')));
        @endif
    </script>
@endsection
