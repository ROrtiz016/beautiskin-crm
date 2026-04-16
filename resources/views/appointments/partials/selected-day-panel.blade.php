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
    $coverageBadge = static function ($appointment): array {
        $activeMembership = optional($appointment->customer)->memberships
            ?->first(fn ($membership) => $membership->status === 'active' && (! $membership->end_date || $membership->end_date->isFuture()));
        $coveredServiceIds = collect($activeMembership?->membership?->coveredServices ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $appointmentServiceIds = $appointment->services
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (! $activeMembership || $appointmentServiceIds->isEmpty()) {
            return [
                'label' => $activeMembership ? 'No coverage rules set' : 'No active membership',
                'class' => 'bg-slate-100 text-slate-700',
            ];
        }

        if ($appointmentServiceIds->diff($coveredServiceIds)->isEmpty()) {
            return [
                'label' => ($activeMembership->membership?->name ?: 'Membership') . ' covers this visit',
                'class' => 'bg-emerald-100 text-emerald-700',
            ];
        }

        if ($appointmentServiceIds->intersect($coveredServiceIds)->isNotEmpty()) {
            return [
                'label' => ($activeMembership->membership?->name ?: 'Membership') . ' partially covers services',
                'class' => 'bg-amber-100 text-amber-700',
            ];
        }

        return [
            'label' => ($activeMembership->membership?->name ?: 'Membership') . ' does not cover booked services',
            'class' => 'bg-rose-100 text-rose-700',
        ];
    };
@endphp

<p class="mt-1 text-xs text-slate-500" id="selectedDayDateLabel">{{ $selectedDate->format('Y-m-d') }}</p>
<div class="mt-4 space-y-3" id="selectedDayList">
    @forelse ($selectedAppointments as $appointment)
        @php
            $activeMembership = optional($appointment->customer)->memberships
                ?->first(fn ($membership) => $membership->status === 'active' && (! $membership->end_date || $membership->end_date->isFuture()));
            $membershipName = $activeMembership?->membership?->name;
            $notesPreview = trim((string) $appointment->notes);
            $coverage = $coverageBadge($appointment);
        @endphp
        <div class="rounded-lg border border-slate-200 px-3 py-3">
            <div class="flex items-start justify-between gap-3">
                <button
                    type="button"
                    class="min-w-0 flex-1 text-left hover:text-pink-700 js-draggable-appointment"
                    onclick="openAppointmentDetailsModal(this)"
                    draggable="true"
                    data-id="{{ $appointment->id }}"
                    data-customer-id="{{ $appointment->customer_id }}"
                    data-time="{{ optional($appointment->scheduled_at)->format('g:i A') }} - {{ optional($appointment->ends_at)->format('g:i A') ?: 'TBD' }}"
                    data-customer-name="{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}"
                    data-customer-email="{{ $appointment->customer?->email ?: '-' }}"
                    data-customer-phone="{{ $appointment->customer?->phone ?: '-' }}"
                    data-customer-membership="{{ $membershipName ?: 'No active membership' }}"
                    data-services="{{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services selected' }}"
                    data-services-json='@json($appointment->services->map(fn ($service) => ["service_id" => $service->service_id, "quantity" => $service->quantity])->values())'
                    data-arrived="{{ $appointment->arrived_confirmed ? '1' : '0' }}"
                    data-arrival-action="{{ route('appointments.arrival.update', $appointment) }}"
                    data-staff-user-id="{{ $appointment->staff_user_id ?? '' }}"
                    data-staff-name="{{ $appointment->staffUser?->name ?: 'Unassigned' }}"
                    data-staff-action="{{ route('appointments.staff.update', $appointment) }}"
                    data-reschedule-action="{{ route('appointments.reschedule', $appointment) }}"
                    data-reminder-action="{{ route('appointments.reminders.email', $appointment) }}"
                    data-update-action="{{ route('appointments.update', $appointment) }}"
                    data-status-action="{{ route('appointments.status.update', $appointment) }}"
                    data-scheduled-at="{{ optional($appointment->scheduled_at)->format('Y-m-d\TH:i') }}"
                    data-ends-at="{{ optional($appointment->ends_at)->format('Y-m-d\TH:i') }}"
                    data-notes="{{ $appointment->notes }}"
                    data-status="{{ $appointment->status }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium">{{ optional($appointment->scheduled_at)->format('g:i A') }} - {{ optional($appointment->ends_at)->format('g:i A') ?: 'TBD' }}</p>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($appointment->status) }}">
                            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm">{{ $appointment->customer?->first_name }} {{ $appointment->customer?->last_name }}</p>
                    <p class="text-xs text-slate-500">Staff: {{ $appointment->staffUser?->name ?: 'Unassigned' }}</p>
                    <p class="mt-1">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $coverage['class'] }}">
                            {{ $coverage['label'] }}
                        </span>
                    </p>
                    <p class="text-xs text-slate-500">
                        Services: {{ $appointment->services->pluck('service_name')->filter()->implode(', ') ?: 'No services selected' }}
                    </p>
                    @if ($appointment->email_reminder_sent_at)
                        <p class="mt-1 text-xs text-slate-500">Reminder emailed {{ $appointment->email_reminder_sent_at->format('Y-m-d g:i A') }}</p>
                    @endif
                    @if ($notesPreview !== '')
                        <p class="mt-1 text-xs text-slate-500">Notes: {{ \Illuminate\Support\Str::limit($notesPreview, 100) }}</p>
                    @endif
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    onclick="openEditAppointmentModal(this.previousElementSibling)"
                >
                    Edit
                </button>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('appointments.reminders.email', $appointment) }}">
                    @csrf
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Email reminder</button>
                </form>
                @if ($appointment->status === 'booked')
                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="completed">
                        <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Complete</button>
                    </form>
                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Cancel</button>
                    </form>
                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="no_show">
                        <button class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">No-show</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('appointments.status.update', $appointment) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="booked">
                        <button class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Undo to booked</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">No appointments on this day.</p>
    @endforelse
</div>

<div class="mt-6 border-t border-slate-200 pt-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Waitlist / Standby</h3>
        <span class="text-xs text-slate-400">{{ $selectedWaitlistEntries->count() }} active</span>
    </div>
    <div class="mt-3 space-y-3">
        @forelse ($selectedWaitlistEntries as $entry)
            <div class="rounded-lg border border-slate-200 px-3 py-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $entry->customer?->first_name }} {{ $entry->customer?->last_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $entry->service?->name ?: 'Any service' }}
                            @if ($entry->staffUser)
                                | Preferred staff: {{ $entry->staffUser->name }}
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">
                            Preferred time:
                            {{ $entry->preferred_start_time ?: 'Any time' }}
                            @if ($entry->preferred_end_time)
                                - {{ $entry->preferred_end_time }}
                            @endif
                        </p>
                        @if ($entry->notes)
                            <p class="mt-1 text-xs text-slate-500">Notes: {{ \Illuminate\Support\Str::limit($entry->notes, 100) }}</p>
                        @endif
                    </div>
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                        {{ ucfirst($entry->status) }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($entry->status !== 'contacted')
                        <form method="POST" action="{{ route('appointments.waitlist.status.update', $entry) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="contacted">
                            <button class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Mark contacted</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('appointments.waitlist.status.update', $entry) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="booked">
                        <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Marked booked</button>
                    </form>
                    <form method="POST" action="{{ route('appointments.waitlist.status.update', $entry) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Remove</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">No standby customers for this day.</p>
        @endforelse
    </div>
</div>

<div class="mt-6 border-t border-slate-200 pt-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Staff Availability</h3>
        <span class="text-xs text-slate-400">Grouped for {{ $selectedDate->format('Y-m-d') }}</span>
    </div>
    <div class="mt-3 space-y-3">
        @foreach ($staffAvailability as $row)
            <div class="rounded-lg border border-slate-200 px-3 py-2">
                <div class="flex items-center justify-between">
                    <p class="font-medium">{{ $row['label'] }}</p>
                    <span class="text-xs text-slate-500">{{ $row['count'] }} {{ $row['count'] === 1 ? 'appointment' : 'appointments' }}</span>
                </div>
                @if ($row['count'] > 0)
                    <div class="mt-2 space-y-1 text-xs text-slate-600">
                        @foreach ($row['appointments'] as $staffAppointment)
                            <p>
                                {{ optional($staffAppointment->scheduled_at)->format('g:i A') }}
                                -
                                {{ optional($staffAppointment->ends_at)->format('g:i A') ?: 'TBD' }}
                                :
                                {{ $staffAppointment->customer?->first_name }} {{ $staffAppointment->customer?->last_name }}
                            </p>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-xs text-emerald-700">Available all day in current filtered view.</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
