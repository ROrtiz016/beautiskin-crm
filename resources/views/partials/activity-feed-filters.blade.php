@php
    /** @var string $action */
    /** @var string $clearUrl */
    /** @var array<string, string> $categoryLabels */
    /** @var bool $showCustomerField */
    $fid = $filterFormId ?? ($showCustomerField ? 'activity-global-filters' : 'activity-customer-filters');
@endphp

<div class="crm-panel mb-6 p-5">
    <div class="border-b border-slate-200 pb-4">
        <h2 class="text-lg font-semibold text-slate-900">Filters</h2>
        <p class="mt-1 text-xs text-slate-500">
            @if ($showCustomerField)
                Narrow the org-wide feed by keywords, customer, activity type, or date range.
            @else
                Search this customer&rsquo;s timeline or limit by type and dates.
            @endif
        </p>
    </div>

    <form id="{{ $fid }}" method="GET" action="{{ $action }}" class="mt-5 space-y-6">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
            <div class="{{ $showCustomerField ? 'lg:col-span-6' : 'lg:col-span-12' }}">
                <label for="{{ $fid }}_q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search summary</label>
                <input
                    id="{{ $fid }}_q"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Words in the activity text…"
                    class="crm-input text-sm"
                    autocomplete="off"
                >
            </div>
            @if ($showCustomerField)
                <div class="lg:col-span-6">
                    <label for="{{ $fid }}_customer" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</label>
                    <input
                        id="{{ $fid }}_customer"
                        type="search"
                        name="customer"
                        value="{{ request('customer') }}"
                        placeholder="Name, email, or phone…"
                        class="crm-input text-sm"
                        autocomplete="off"
                    >
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end lg:gap-5">
            <div class="sm:col-span-2 lg:col-span-4">
                <label for="{{ $fid }}_category" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Activity type</label>
                <select id="{{ $fid }}_category" name="category" class="crm-input text-sm">
                    <option value="">All types</option>
                    @foreach ($categoryLabels as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-3">
                <label for="{{ $fid }}_from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">From date</label>
                <input id="{{ $fid }}_from" type="date" name="from" value="{{ request('from') }}" class="crm-input text-sm">
            </div>
            <div class="lg:col-span-3">
                <label for="{{ $fid }}_to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">To date</label>
                <input id="{{ $fid }}_to" type="date" name="to" value="{{ request('to') }}" class="crm-input text-sm">
            </div>
            <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row sm:flex-wrap sm:items-center lg:col-span-2 lg:flex-row lg:justify-end lg:self-end">
                <button type="submit" class="crm-btn-primary w-full justify-center text-sm sm:w-auto">Apply</button>
                <a href="{{ $clearUrl }}" class="crm-btn-secondary w-full justify-center text-center text-sm sm:w-auto">Clear all</a>
            </div>
        </div>
    </form>
</div>
