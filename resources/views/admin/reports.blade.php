@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Reports</h1>
            <p class="mt-1 text-sm text-slate-600">Appointment volume, completion revenue, and service mix for a selected period ({{ $clinicTimezone }}).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.operations.index') }}" class="text-sm font-semibold text-pink-700 hover:text-pink-800">Operations</a>
            <a href="{{ route('admin.control-board') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Admin board</a>
        </div>
    </div>

    <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
                <input type="date" name="from" value="{{ $fromDate }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
                <input type="date" name="to" value="{{ $toDate }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
            <a
                href="{{ route('admin.reports.export', array_filter(request()->only(['from', 'to']))) }}"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Download CSV (daily)
            </a>
        </form>
        <p class="mt-3 text-xs text-slate-500">Showing {{ $rangeLabel }} · Up to 366 days.</p>
    </section>

    <section class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed revenue</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">${{ number_format($completedRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sum of <span class="font-medium">total_amount</span> for completed appointments in range.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scheduled volume</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($appointmentVolume) }}</p>
            <p class="mt-1 text-xs text-slate-500">Appointments in range excluding cancelled.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">No-shows</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($noShowCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">By scheduled date in range.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New customers</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($newCustomers) }}</p>
            <p class="mt-1 text-xs text-slate-500">Profiles created in range.</p>
        </div>
    </section>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Appointments by status</h2>
            <p class="mt-1 text-xs text-slate-500">Counts by <span class="font-medium">scheduled_at</span> falling in the range.</p>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach (['booked', 'completed', 'cancelled', 'no_show'] as $st)
                    <li class="flex justify-between border-b border-slate-100 py-1">
                        <span class="capitalize text-slate-700">{{ str_replace('_', ' ', $st) }}</span>
                        <span class="font-semibold text-slate-900">{{ number_format((int) ($statusCounts[$st] ?? 0)) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-slate-500">Waitlist entries created in range: <span class="font-semibold text-slate-800">{{ number_format($waitlistOpened) }}</span></p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Top services (by line revenue)</h2>
            <p class="mt-1 text-xs text-slate-500">From appointment line items on non-cancelled appointments.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-3 font-semibold">Service</th>
                            <th class="py-2 pr-3 font-semibold">Units</th>
                            <th class="py-2 font-semibold">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topServices as $row)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 text-slate-800">{{ $row->service_name ?: '—' }}</td>
                                <td class="py-2 pr-3 text-slate-600">{{ number_format((int) $row->units) }}</td>
                                <td class="py-2 font-medium text-slate-900">${{ number_format((float) $row->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-slate-500">No service lines in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Daily breakdown</h2>
        <p class="mt-1 text-xs text-slate-500">Per clinic-local day: scheduled volume (excl. cancelled) and completed revenue.</p>
        <div class="mt-4 max-h-[28rem] overflow-y-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 border-b border-slate-200 bg-white text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Date</th>
                        <th class="py-2 pr-4 font-semibold">Scheduled</th>
                        <th class="py-2 font-semibold">Completed revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyRows as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4 font-mono text-xs text-slate-700">{{ $row['date'] }}</td>
                            <td class="py-2 pr-4 text-slate-700">{{ number_format($row['scheduled_count']) }}</td>
                            <td class="py-2 text-slate-900">${{ number_format($row['completed_revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
