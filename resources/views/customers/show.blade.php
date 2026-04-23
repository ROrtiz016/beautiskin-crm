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
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $customer->first_name }} {{ $customer->last_name }}</h1>
            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">Profile, bookings, payments, and memberships for this client.</p>
        </div>
        <a href="{{ route('customers.index') }}" class="crm-btn-secondary text-sm">
            ← Back to customers
        </a>
    </div>

    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="crm-panel p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Email</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit email" onclick="openContactEditModal('email')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->email ?: '-' }}</p>
        </div>
        <div class="crm-panel p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit phone" onclick="openContactEditModal('phone')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->phone ?: '-' }}</p>
        </div>
        <div class="crm-panel p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500">Date of Birth</p>
                <button type="button" class="text-slate-500 hover:text-slate-800" title="Edit date of birth" onclick="openContactEditModal('date_of_birth')">✎</button>
            </div>
            <p class="mt-1 font-medium">{{ $customer->date_of_birth?->format('Y-m-d') ?: '-' }}</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total Paid</p>
            <p class="mt-1 font-medium">${{ number_format((float) $totalSpent, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sum of all completed appointments (not limited to the lists below).</p>
        </div>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <section class="crm-panel p-5">
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
                            <button
                                type="button"
                                class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                                onclick="openCustomerCancelAppointmentModal(@js(route('customers.appointments.status', [$customer, $nextAppointment])))"
                            >
                                Mark cancelled
                            </button>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No upcoming booked appointment.</p>
                @endif
            </div>
        </section>

        <section class="crm-panel p-5 lg:col-span-2">
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
                    <div class="rounded-lg border border-slate-300/90 bg-slate-50/50 px-3 py-2 shadow-sm">
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
                            <button
                                type="button"
                                class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                                onclick="openCustomerCancelAppointmentModal(@js(route('customers.appointments.status', [$customer, $appointment])))"
                            >
                                Mark cancelled
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No booked appointments scheduled.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mb-6 crm-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Sales opportunities</h2>
                <p class="mt-1 text-xs text-slate-500">Pipeline deals linked to this customer (most recently updated first).</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('sales.pipeline.index', ['customer_id' => $customer->id]) }}" class="crm-btn-secondary text-sm">View in pipeline</a>
                <a href="{{ route('tasks.index', ['customer_id' => $customer->id, 'view' => 'all_pending']) }}" class="crm-btn-secondary text-sm">Tasks</a>
            </div>
        </div>
        <div class="mt-4 space-y-2">
            @php $oppLabels = \App\Models\Opportunity::stageLabels(); @endphp
            @forelse ($customer->opportunities as $opp)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $opp->title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span class="inline-flex rounded-full bg-white px-2 py-0.5 font-semibold text-slate-700 ring-1 ring-slate-200">{{ $oppLabels[$opp->stage] ?? $opp->stage }}</span>
                            <span class="ml-1 tabular-nums">${{ number_format((float) $opp->amount, 2) }}</span>
                            @if ($opp->expected_close_date)
                                · Close {{ $opp->expected_close_date->format('M j, Y') }}
                            @endif
                            @if ($opp->owner)
                                · {{ $opp->owner->name }}
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No opportunities yet. Add one from the pipeline.</p>
            @endforelse
        </div>
    </section>

    <section class="mb-6 crm-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Tasks &amp; follow-ups</h2>
                <p class="mt-1 text-xs text-slate-500">Open and recent tasks for this customer.</p>
            </div>
            <a href="{{ route('tasks.index', ['customer_id' => $customer->id, 'view' => 'all_pending']) }}" class="crm-btn-secondary text-sm shrink-0">Open task queue</a>
        </div>
        <div class="mt-4 space-y-2">
            @php $taskKinds = \App\Models\Task::kindLabels(); @endphp
            @forelse ($customer->tasks as $task)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $task->title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span class="inline-flex rounded-full bg-white px-2 py-0.5 font-semibold text-slate-700 ring-1 ring-slate-200">{{ ucfirst($task->status) }}</span>
                            <span class="ml-1">{{ $taskKinds[$task->kind] ?? $task->kind }}</span>
                            · Due {{ $task->due_at->timezone(\App\Services\AppointmentPolicyEnforcer::clinicTimezone())->format('M j, Y g:i A') }}
                            @if ($task->assignedTo)
                                · {{ $task->assignedTo->name }}
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No tasks yet. Create one from the task queue.</p>
            @endforelse
        </div>
    </section>

    <section class="mb-6 crm-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Activity timeline</h2>
                <p class="mt-1 text-xs text-slate-500">Notes, tasks, appointments, payments, communications, and pipeline updates (preview — newest first).</p>
            </div>
            <a href="{{ route('customers.timeline.show', $customer) }}" class="crm-btn-secondary shrink-0 text-sm">Full timeline</a>
        </div>
        <form method="POST" action="{{ route('customers.timeline-notes.store', $customer) }}" class="mt-4 space-y-2 rounded-lg border border-slate-200 bg-slate-50/50 p-3">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Add note</label>
            <textarea name="summary" rows="2" class="crm-input" placeholder="Log a call, outcome, or next step for the team." required>{{ old('summary') }}</textarea>
            @error('summary') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="crm-btn-primary text-sm">Save to timeline</button>
        </form>
        <form method="POST" action="{{ route('customers.communications.store', $customer) }}" class="mt-4 space-y-2 rounded-lg border border-slate-200 bg-slate-50/50 p-3">
            @csrf
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick log — call / email / SMS</p>
            <div class="flex flex-wrap gap-2">
                <select name="channel" class="crm-input max-w-[11rem] text-sm" required>
                    <option value="call">Phone call</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
                <input type="text" name="summary" class="crm-input min-w-0 flex-1 text-sm" placeholder="Short summary…" required>
                <button type="submit" class="crm-btn-secondary text-sm">Log</button>
            </div>
            @error('channel') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error('summary') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </form>
        <ul class="mt-4 space-y-3 border-t border-slate-200 pt-4">
            @php
                $activityCategoryLabels = \App\Models\CustomerActivity::categoryLabels();
            @endphp
            @forelse ($customer->activities as $activity)
                <li class="text-sm">
                    <p class="text-xs text-slate-500">
                        {{ $activity->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                        @if ($activity->user)
                            · {{ $activity->user->name }}
                        @endif
                        · <span class="font-semibold text-slate-700">{{ $activityCategoryLabels[$activity->category] ?? ucfirst(str_replace('_', ' ', (string) $activity->event_type)) }}</span>
                    </p>
                    <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ $activity->summary }}</p>
                </li>
            @empty
                <li class="text-sm text-slate-500">No activity yet.</li>
            @endforelse
        </ul>
    </section>

    <div id="contactEditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-md">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Contact Details</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeContactEditModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('customers.contact.update', $customer) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Email</label>
                    <input id="contact_email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="crm-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Phone</label>
                    <input id="contact_phone" name="phone" value="{{ old('phone', $customer->phone) }}" class="crm-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Date of birth</label>
                    <input id="contact_dob" name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}" class="crm-input">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeContactEditModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <section class="mb-6 crm-panel p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold">Past Appointments</h2>
            <a href="{{ route('quotes.index', ['customer_id' => $customer->id]) }}" class="text-xs font-semibold text-pink-700 hover:text-pink-800">Quotes for this customer →</a>
        </div>
        <p class="mt-1 text-xs text-slate-500">Showing up to {{ number_format($appointmentsProfileDisplayLimit) }} rows by latest scheduled time (includes upcoming in that window).</p>
        <div class="mt-4 space-y-3">
            @forelse ($pastAppointments as $appointment)
                <div class="rounded-lg border border-slate-300/90 bg-slate-50/50 px-3 py-2 shadow-sm">
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
                    @php
                        $visitTot = round((float) $appointment->total_amount, 2);
                        $paidTot = round((float) ($appointment->payment_entries_sum_amount ?? 0), 2);
                        $dueTot = round(max(0, $visitTot - $paidTot), 2);
                    @endphp
                    <p class="mt-1 text-xs text-slate-600">
                        Visit total <span class="font-semibold">${{ number_format($visitTot, 2) }}</span>
                        · Payments recorded <span class="font-semibold">${{ number_format($paidTot, 2) }}</span>
                        · Balance <span class="font-semibold {{ $dueTot > 0 ? 'text-amber-800' : 'text-emerald-800' }}">${{ number_format($dueTot, 2) }}</span>
                        @if ($appointment->quote)
                            · <a href="{{ route('quotes.show', $appointment->quote) }}" class="text-pink-700 hover:text-pink-800">Quote #{{ $appointment->quote->id }}</a>
                        @endif
                    </p>
                    @if ($appointment->status === 'completed' && $retailSaleServices->isNotEmpty())
                        <form method="POST" action="{{ route('customers.appointments.retail-lines.store', [$customer, $appointment]) }}" class="mt-3 flex flex-col gap-2 rounded-md border border-pink-200/80 bg-pink-50/40 px-3 py-2 sm:flex-row sm:flex-wrap sm:items-end">
                            @csrf
                            <div class="min-w-0 flex-1">
                                <label class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-pink-900/80">Add retail (in-room)</label>
                                <select name="service_id" class="crm-input text-sm" required>
                                    <option value="">Choose product…</option>
                                    @foreach ($retailSaleServices as $rs)
                                        <option value="{{ $rs->id }}" @selected((string) old('service_id') === (string) $rs->id)>
                                            {{ $rs->name }}
                                            @if ($rs->track_inventory)
                                                ({{ (int) $rs->stock_quantity }} in stock)
                                            @endif
                                            — ${{ number_format((float) $rs->price, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="w-24 shrink-0">
                                <label class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-pink-900/80">Qty</label>
                                <input type="number" name="quantity" min="1" max="999" value="{{ old('quantity', 1) }}" class="crm-input text-sm" required>
                                @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="crm-btn-primary shrink-0 text-xs py-2">Add line</button>
                        </form>
                    @endif
                    @if ($appointment->status === 'cancelled' && $appointment->cancellation_reason)
                        <p class="mt-2 text-xs text-slate-600">
                            <span class="font-semibold text-slate-700">Cancellation:</span>
                            {{ \Illuminate\Support\Str::limit($appointment->cancellation_reason, 160) }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Logged by {{ $appointment->cancelledBy?->name ?: 'Unknown' }}
                            @if ($appointment->cancelled_at)
                                · {{ $appointment->cancelled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                            @endif
                            @if ($appointment->sales_follow_up_needed)
                                · <span class="font-semibold text-amber-800">Sales follow-up</span>
                            @endif
                        </p>
                    @endif
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

    <div id="addAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Book New Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeAddAppointmentModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('customers.appointments.store', $customer) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">Scheduled At</label>
                    <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="crm-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Ends At</label>
                    <input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="crm-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select name="staff_user_id" class="crm-input">
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
                    <textarea name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                </div>
                @if ($clinicSettings->deposit_required)
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3">
                        <label class="flex items-start gap-2 text-sm text-amber-950">
                            <input type="checkbox" name="deposit_paid" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('deposit_paid'))>
                            <span>
                                Deposit collected (required)
                                @if ($clinicSettings->default_deposit_amount)
                                    — default <span class="font-semibold">${{ number_format((float) $clinicSettings->default_deposit_amount, 2) }}</span>
                                @endif
                            </span>
                        </label>
                        @error('deposit_paid') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeAddAppointmentModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Save appointment</button>
                </div>
            </form>
        </div>
    </div>

    <div id="updateAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Update Appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeUpdateAppointmentModal()">✕</button>
            </div>
            <form id="updateAppointmentForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Scheduled At</label>
                    <input id="update_scheduled_at" name="scheduled_at" type="datetime-local" class="crm-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Ends At</label>
                    <input id="update_ends_at" name="ends_at" type="datetime-local" class="crm-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Staff (optional)</label>
                    <select id="update_staff_user_id" name="staff_user_id" class="crm-input">
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
                    <textarea id="update_notes" name="notes" rows="3" class="crm-input"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeUpdateAppointmentModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="customerCancelAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-md">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Cancel appointment</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCustomerCancelAppointmentModal()">✕</button>
            </div>
            <p class="text-sm text-slate-600">
                Recording cancellation for <span class="font-medium text-slate-800">{{ $customer->first_name }} {{ $customer->last_name }}</span>
            </p>
            <form id="customerCancelAppointmentForm" method="POST" action="" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_type" value="cancel_customer">
                <input type="hidden" name="status" value="cancelled">
                <input type="hidden" name="cancel_appointment_action" id="customer_cancel_appointment_action_field" value="{{ old('cancel_appointment_action') }}">
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
                    <button type="button" class="crm-btn-secondary" onclick="closeCustomerCancelAppointmentModal()">Close</button>
                    <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirm cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold">Payment History</h2>
            <p class="mt-1 text-xs text-slate-500">Up to {{ number_format($paymentHistoryDisplayLimit) }} most recent completed visits.</p>
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

        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold">Services Received</h2>
            <div class="mt-4 space-y-3">
                @forelse ($servicesReceived as $service)
                    <div class="rounded-lg border border-slate-300/90 bg-slate-50/50 px-3 py-2 shadow-sm">
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
        <section class="crm-panel p-5">
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

        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold">Past Memberships</h2>
            <div class="mt-4 space-y-3">
                @forelse ($pastMemberships as $membershipRecord)
                    <div class="rounded-lg border border-slate-300/90 bg-slate-50/50 px-3 py-2 shadow-sm">
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
        <section class="mt-6 crm-panel p-5">
            <h2 class="text-lg font-semibold">Notes</h2>
            <p class="mt-3 text-sm text-slate-700">{{ $customer->notes }}</p>
        </section>
    @endif

    <script>
        const addAppointmentModal = document.getElementById('addAppointmentModal');
        const updateAppointmentModal = document.getElementById('updateAppointmentModal');
        const updateAppointmentForm = document.getElementById('updateAppointmentForm');
        const contactEditModal = document.getElementById('contactEditModal');
        const customerCancelAppointmentModal = document.getElementById('customerCancelAppointmentModal');
        const servicesOptions = @json($serviceOptions);

        function openCustomerCancelAppointmentModal(actionUrl) {
            if (!actionUrl || !customerCancelAppointmentModal) return;
            const form = document.getElementById('customerCancelAppointmentForm');
            form.action = actionUrl;
            const actionField = document.getElementById('customer_cancel_appointment_action_field');
            if (actionField) {
                actionField.value = actionUrl;
            }
            customerCancelAppointmentModal.classList.remove('hidden');
            customerCancelAppointmentModal.classList.add('flex');
        }

        function closeCustomerCancelAppointmentModal() {
            if (!customerCancelAppointmentModal) return;
            customerCancelAppointmentModal.classList.add('hidden');
            customerCancelAppointmentModal.classList.remove('flex');
        }

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
                <select name="services[${index}][service_id]" class="crm-input">
                    ${serviceOptionsHtml(serviceId)}
                </select>
                <input name="services[${index}][quantity]" type="number" min="1" value="${quantity || 1}" class="crm-input max-w-[6rem]">
                <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-100">Remove</button>
            `;
            wrapper.querySelector('button').addEventListener('click', () => {
                wrapper.remove();
            });
            container.appendChild(wrapper);
        }

        @if ($errors->any() && old('form_type') === 'cancel_customer')
            openCustomerCancelAppointmentModal(@json(old('cancel_appointment_action', '')));
        @endif
    </script>
@endsection
