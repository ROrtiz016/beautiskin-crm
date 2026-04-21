@php
    $compact = $compact ?? false;
    $o = $opportunity;
@endphp
<div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
    <p class="text-sm font-semibold leading-snug text-slate-900">{{ $o->title }}</p>
    <p class="mt-1 text-xs text-slate-600">
        <a href="{{ route('customers.show', $o->customer) }}" class="font-medium text-pink-700 hover:text-pink-800">{{ $o->customer?->first_name }} {{ $o->customer?->last_name }}</a>
    </p>
    <p class="mt-1 text-xs text-slate-500">
        <span class="font-medium text-slate-700">${{ number_format((float) $o->amount, 2) }}</span>
        @if ($o->expected_close_date)
            · Close {{ $o->expected_close_date->format('M j, Y') }}
        @else
            · No close date
        @endif
    </p>
    @if ($o->owner)
        <p class="mt-0.5 text-[11px] text-slate-500">Owner: {{ $o->owner->name }}</p>
    @endif
    @if ($o->stage === \App\Models\Opportunity::STAGE_LOST && $o->loss_reason)
        <p class="mt-1 text-[11px] text-rose-800">{{ \Illuminate\Support\Str::limit($o->loss_reason, 120) }}</p>
    @endif
    @if (! $compact)
        <form method="POST" action="{{ route('sales.opportunities.stage', $o) }}" class="mt-2">
            @csrf
            @method('PATCH')
            <input type="hidden" name="pipeline_customer_id" value="{{ $pipelineCustomerHidden }}">
            <label class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Move to</label>
            <select
                name="stage"
                class="crm-input py-1.5 text-xs"
                data-original-stage="{{ $o->stage }}"
                onchange="handleQuickStageChange(this)"
            >
                @foreach (array_merge(\App\Models\Opportunity::pipelineStages(), \App\Models\Opportunity::closedStages()) as $st)
                    <option value="{{ $st }}" @selected($o->stage === $st)>{{ $stageLabels[$st] }}</option>
                @endforeach
            </select>
        </form>
    @endif
    @php
        $editPayload = [
            'update_url' => route('sales.opportunities.update', $o),
            'customer_id' => $o->customer_id,
            'title' => $o->title,
            'amount' => (string) $o->amount,
            'expected_close' => optional($o->expected_close_date)?->format('Y-m-d'),
            'stage' => $o->stage,
            'owner_id' => $o->owner_user_id,
            'notes' => $o->notes,
            'loss_reason' => $o->loss_reason,
        ];
    @endphp
    <button
        type="button"
        class="mt-2 w-full rounded-md border border-slate-200 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
        onclick="openEditOpportunityModal(this)"
        data-payload='@json($editPayload)'
    >
        Edit
    </button>
</div>
