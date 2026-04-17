@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Leads</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">
            Waitlist and standby requests tied to a customer profile. Update status as you reach out or book. Add new entries from the
            <a href="{{ route('appointments.index') }}" class="font-semibold text-pink-700 hover:text-pink-800">Appointments</a>
            calendar (waitlist button on a day).
        </p>
    </div>

    <div class="mb-6">
        @include('home.partials.lead-funnel-widget')
    </div>

    <section class="mb-6 crm-panel p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Attribution</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Lead sources</h2>
                <p class="mt-1 max-w-2xl text-xs text-slate-500">
                    Share of waitlist leads by channel (same filters as the list). Sources are set when a lead is added from the appointments waitlist form.
                </p>
            </div>
        </div>
        @if ($leadSourceChart['hasData'])
            <div class="mt-6 grid items-start gap-8 lg:grid-cols-2">
                <div class="mx-auto max-w-md lg:mx-0">
                    <canvas id="leadSourceChart" height="240" aria-label="Lead sources doughnut chart"></canvas>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Breakdown</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($leadSourceChart['items'] as $row)
                            <li class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                                <span class="flex items-center gap-2 text-slate-700">
                                    <span class="inline-block size-2.5 shrink-0 rounded-sm" style="background-color: {{ $row['color'] }}"></span>
                                    {{ $row['label'] }}
                                </span>
                                <span class="tabular-nums text-slate-900">
                                    <span class="font-semibold">{{ $row['percent'] }}%</span>
                                    <span class="text-slate-500">({{ number_format($row['count']) }})</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-slate-500">Total in view: <span class="font-semibold text-slate-800">{{ number_format($leadSourceChart['total']) }}</span> leads</p>
                </div>
            </div>
        @else
            <p class="mt-4 text-sm text-slate-600">No waitlist leads match the current filters, so there is nothing to chart.</p>
        @endif
    </section>

    <section class="mb-6 crm-panel p-5">
        <div class="mb-4 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">List filters</p>
            <h2 class="mt-1 text-lg font-semibold text-slate-900">Refine leads</h2>
        </div>
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900" role="alert">
                {{ $errors->first() }}
            </div>
        @endif
        <form method="GET" action="{{ route('leads.index') }}" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="min-w-0 lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Name, email, phone…"
                        class="crm-input py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                    <select name="status" class="crm-input w-full py-2 text-sm">
                        <option value="" @selected($statusFilter === '')>All statuses</option>
                        @foreach ($statusLabels as $st)
                            <option value="{{ $st }}" @selected($statusFilter === $st)>
                                {{ ucfirst($st) }}
                                @php $c = (int) ($countsByStatus[$st] ?? 0); @endphp
                                @if ($c > 0)
                                    ({{ number_format($c) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Preferred from</label>
                    <input type="date" name="preferred_from" value="{{ $preferredFrom }}" class="crm-input w-full py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Preferred to</label>
                    <input type="date" name="preferred_to" value="{{ $preferredTo }}" class="crm-input w-full py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Service</label>
                    <select name="service_id" class="crm-input w-full py-2 text-sm">
                        <option value="" @selected($serviceIdFilter === 0)>Any service</option>
                        @foreach ($serviceOptions as $svc)
                            <option value="{{ $svc->id }}" @selected((int) $serviceIdFilter === (int) $svc->id)>{{ $svc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned staff</label>
                    <select name="assigned_to" class="crm-input w-full py-2 text-sm">
                        <option value="" @selected($assignedToFilter === '')>Anyone</option>
                        <option value="none" @selected($assignedToFilter === 'none')>Unassigned</option>
                        @foreach ($staffOptions as $u)
                            <option value="{{ $u->id }}" @selected($assignedToFilter === (string) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Added on or after</label>
                    <input type="date" name="created_from" value="{{ $createdFrom }}" class="crm-input w-full py-2 text-sm">
                </div>
            </div>
            <p class="text-xs text-slate-500">
                Status counts in the status dropdown are clinic-wide. Filters here apply to the lead list below and the attribution chart above.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Apply filters</button>
                @if ($hasActiveFilters)
                    <a href="{{ route('leads.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Clear all</a>
                @endif
            </div>
        </form>
    </section>

    <section class="crm-panel overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Preferred</th>
                        <th class="px-4 py-3">Service / staff</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="min-w-[10rem] px-4 py-3">Contact log</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($entries as $entry)
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                @if ($entry->customer && ! $entry->customer->trashed())
                                    <a href="{{ route('customers.show', $entry->customer) }}" class="font-medium text-pink-800 hover:text-pink-900">
                                        {{ $entry->customer->first_name }} {{ $entry->customer->last_name }}
                                    </a>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $entry->customer->email ?: '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $entry->customer->phone ?: '' }}</p>
                                @else
                                    <span class="font-medium text-slate-700">Customer unavailable</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <span class="tabular-nums">{{ optional($entry->preferred_date)->format('M j, Y') }}</span>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    @php
                                        $startT = $entry->preferred_start_time ? substr((string) $entry->preferred_start_time, 0, 5) : null;
                                        $endT = $entry->preferred_end_time ? substr((string) $entry->preferred_end_time, 0, 5) : null;
                                    @endphp
                                    {{ $startT ?: 'Any' }}
                                    @if ($endT)
                                        – {{ $endT }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <p>{{ $entry->service?->name ?: 'Any service' }}</p>
                                @if ($entry->staffUser)
                                    <p class="mt-0.5 text-xs text-slate-500">Staff: {{ $entry->staffUser->name }}</p>
                                @endif
                                @if ($entry->notes)
                                    <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($entry->notes, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-800">
                                    {{ $entry->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ \App\Support\LeadSource::label($entry->lead_source ?? 'unknown') }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @if ($entry->status === 'contacted' && $entry->contacted_at)
                                    <p class="font-medium text-slate-800">
                                        {{ $entry->contacted_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    </p>
                                    <p class="mt-0.5">{{ \App\Support\ContactMethod::label($entry->contact_method ?? '') }}</p>
                                    @if ($entry->contactedBy)
                                        <p class="mt-0.5 text-slate-500">By {{ $entry->contactedBy->name }}</p>
                                    @endif
                                    @if ($entry->contact_notes)
                                        <p class="mt-1 text-slate-600" title="{{ $entry->contact_notes }}">{{ \Illuminate\Support\Str::limit($entry->contact_notes, 120) }}</p>
                                    @endif
                                @elseif ($entry->status === 'contacted')
                                    <span class="text-slate-500">No log on file</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($entry->status !== 'contacted')
                                        <button
                                            type="button"
                                            class="rounded-md bg-slate-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                            data-open-waitlist-contact
                                            data-contact-url="{{ route('appointments.waitlist.contact', $entry) }}"
                                            data-return-to="leads"
                                        >
                                            Log contact
                                        </button>
                                    @endif
                                    @if ($entry->status !== 'booked')
                                        <form method="POST" action="{{ route('appointments.waitlist.status.update', $entry) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="booked">
                                            <input type="hidden" name="return_to" value="leads">
                                            <button type="submit" class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Booked</button>
                                        </form>
                                    @endif
                                    @if ($entry->status !== 'cancelled')
                                        <form method="POST" action="{{ route('appointments.waitlist.status.update', $entry) }}" class="inline" onsubmit="return confirm('Remove this waitlist entry?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <input type="hidden" name="return_to" value="leads">
                                            <button type="submit" class="rounded-md border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Remove</button>
                                        </form>
                                    @endif
                                    @if ($entry->customer && ! $entry->customer->trashed() && $entry->preferred_date)
                                        <a
                                            href="{{ route('appointments.index', ['month' => $entry->preferred_date->format('Y-m'), 'date' => $entry->preferred_date->toDateString()]) }}"
                                            class="inline-flex rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                                        >Calendar</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-600">
                                No waitlist entries match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $entries->links() }}
            </div>
        @endif
    </section>

    @include('waitlist.partials.mark-contacted-modal')
@endsection

@if ($leadSourceChart['hasData'])
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('leadSourceChart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                const raw = @json($leadSourceChart['items']);
                const total = {{ (int) $leadSourceChart['total'] }};
                new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: raw.map(function (r) {
                            return r.label + ' (' + r.percent + '%)';
                        }),
                        datasets: [{
                            data: raw.map(function (r) {
                                return r.count;
                            }),
                            backgroundColor: raw.map(function (r) {
                                return r.color;
                            }),
                            borderWidth: 2,
                            borderColor: '#ffffff',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 10 },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const r = raw[ctx.dataIndex];
                                        return ' ' + r.count + ' leads (' + r.percent + '% of ' + total + ')';
                                    },
                                },
                            },
                        },
                    },
                });
            });
        </script>
    @endpush
@endif
