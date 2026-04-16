<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Today’s revenue</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">${{ number_format($todaysRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Completed appointments ({{ $metricsTimezone }})</p>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">No-shows today</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $noShowsToday }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $metricsDateLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waitlist depth</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $waitlistDepth }}</p>
            <p class="mt-1 text-xs text-slate-500">Waiting + contacted (all dates)</p>
        </div>
        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff day load</p>
            <p class="mt-2 text-sm text-slate-600">Booked minutes vs 8h reference ({{ $metricsTimezone }})</p>
        </div>
    </div>
</section>
