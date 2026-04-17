<div class="crm-panel p-5 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lead funnel</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Pipeline snapshot</h3>
            <p class="mt-1 max-w-3xl text-xs text-slate-500">
                <span class="font-medium text-slate-600">New leads</span> are waitlist rows still
                <span class="font-semibold text-slate-700">waiting</span> that were created in the last
                <span class="font-semibold text-slate-700">{{ $leadFunnelRollingDays }} days</span> (clinic day → app storage).
                <span class="font-medium text-slate-600">Contacted</span> is the current waitlist queue in that status.
                <span class="font-medium text-slate-600">New customers</span> and <span class="font-medium text-slate-600">membership sales</span> count records created in the same rolling window.
            </p>
        </div>
        @unless ($leadFunnelHideNav ?? false)
            <a href="{{ route('leads.index') }}" class="shrink-0 text-sm font-semibold text-pink-700 hover:text-pink-800">Open leads →</a>
        @endunless
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New leads</p>
            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($leadFunnelNewLeads) }}</p>
            <p class="mt-1 text-xs text-slate-500">Still awaiting first contact; created in the last {{ $leadFunnelRollingDays }} days.</p>
        </div>
        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contacted</p>
            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($leadFunnelContacted) }}</p>
            <p class="mt-1 text-xs text-slate-500">Waitlist entries still marked contacted.</p>
        </div>
        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New customers</p>
            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($leadFunnelNewCustomers) }}</p>
            <p class="mt-1 text-xs text-slate-500">Profiles added in the last {{ $leadFunnelRollingDays }} days.</p>
        </div>
        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Membership sales</p>
            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($leadFunnelNewMemberships) }}</p>
            <p class="mt-1 text-xs text-slate-500">Subscriptions created in the last {{ $leadFunnelRollingDays }} days.</p>
        </div>
    </div>
</div>
