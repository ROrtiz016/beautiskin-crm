@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Sales</h1>
            <p class="mt-1 text-sm text-slate-600">
                Revenue and volume for appointments scheduled in the selected range ({{ $clinicTimezone }}). New memberships count subscriptions created in the same window.
            </p>
        </div>
        @can('access-admin-board')
            <a href="{{ route('admin.reports.index', request()->only(['from', 'to'])) }}" class="text-sm font-semibold text-pink-700 hover:text-pink-800">Open full reports →</a>
        @endcan
    </div>

    <section class="mb-8 crm-panel p-5">
        <form method="GET" action="{{ route('sales.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
                <input type="date" name="from" value="{{ $fromDate }}" class="crm-input max-w-[11rem] py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
                <input type="date" name="to" value="{{ $toDate }}" class="crm-input max-w-[11rem] py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Apply</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">Showing {{ $rangeLabel }} · Up to 366 days.</p>
    </section>

    <section class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed visit revenue</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">${{ number_format($completedRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sum of <span class="font-medium">total_amount</span> on completed appointments in range.</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Line item revenue</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">${{ number_format($lineItemRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sum of service lines on non-cancelled appointments (scheduled in range).</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New memberships</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($newMemberships) }}</p>
            <p class="mt-1 text-xs text-slate-500">Customer subscriptions created in range.</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scheduled volume</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($appointmentVolume) }}</p>
            <p class="mt-1 text-xs text-slate-500">Appointments in range excluding cancelled.</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed visits</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($completedAppointmentCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">Completed appointments with a scheduled date in range.</p>
        </div>
    </section>

    <section class="crm-panel p-5">
        <h2 class="text-lg font-semibold text-slate-900">Top services &amp; products</h2>
        <p class="mt-1 text-xs text-slate-500">By line revenue on non-cancelled appointments in this range.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-3 font-semibold">Name</th>
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
                            <td colspan="3" class="py-4 text-slate-500">No line items in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
