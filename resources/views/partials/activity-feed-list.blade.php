@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $activities */
    /** @var array<string, string> $categoryLabels */
    /** @var bool $showCustomer */
    $catBadge = static function (string $cat): string {
        return match ($cat) {
            \App\Models\CustomerActivity::CATEGORY_APPOINTMENT => 'bg-pink-100 text-pink-800 ring-pink-200',
            \App\Models\CustomerActivity::CATEGORY_TASK => 'bg-sky-100 text-sky-800 ring-sky-200',
            \App\Models\CustomerActivity::CATEGORY_NOTE => 'bg-slate-100 text-slate-800 ring-slate-200',
            \App\Models\CustomerActivity::CATEGORY_PAYMENT => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            \App\Models\CustomerActivity::CATEGORY_COMMUNICATION => 'bg-violet-100 text-violet-800 ring-violet-200',
            \App\Models\CustomerActivity::CATEGORY_SALES => 'bg-amber-100 text-amber-900 ring-amber-200',
            default => 'bg-slate-50 text-slate-600 ring-slate-200',
        };
    };
@endphp

<ul class="space-y-4 border-t border-slate-200 pt-4">
    @forelse ($activities as $activity)
        <li class="rounded-lg border border-slate-200/90 bg-slate-50/40 px-3 py-3 text-sm shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="text-xs text-slate-500">
                    <span class="font-medium text-slate-700">{{ $activity->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                    @if ($activity->user)
                        <span> · {{ $activity->user->name }}</span>
                    @endif
                </p>
                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $catBadge($activity->category ?? \App\Models\CustomerActivity::CATEGORY_SYSTEM) }}">
                    {{ $categoryLabels[$activity->category] ?? ucfirst((string) $activity->category) }}
                </span>
            </div>
            @if ($showCustomer && $activity->customer)
                <p class="mt-1 text-xs">
                    <a href="{{ route('customers.timeline.show', $activity->customer) }}" class="font-semibold text-pink-700 hover:text-pink-800">
                        {{ $activity->customer->first_name }} {{ $activity->customer->last_name }}
                    </a>
                    @if ($activity->customer->email)
                        <span class="text-slate-500"> · {{ $activity->customer->email }}</span>
                    @endif
                </p>
            @endif
            <p class="mt-2 whitespace-pre-wrap text-slate-800">{{ $activity->summary }}</p>
            @if ($activity->relatedTask)
                <p class="mt-1 text-[11px] text-slate-500">Linked task: {{ $activity->relatedTask->title }}</p>
            @endif
        </li>
    @empty
        <li class="text-sm text-slate-500">No activity matches these filters.</li>
    @endforelse
</ul>

@if ($activities instanceof \Illuminate\Contracts\Pagination\Paginator && $activities->hasPages())
    <div class="mt-6 border-t border-slate-200 pt-4">
        {{ $activities->links() }}
    </div>
@endif
