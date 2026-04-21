@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Inventory &amp; retail</h1>
            <p class="mt-1 text-sm text-slate-600">
                Retail SKUs and anything with stock tracking. Low-stock items use the reorder level set on each service.
            </p>
        </div>
        <a href="{{ route('services.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
            Edit catalog
        </a>
    </div>

    @if ($lowStockItems->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-300/90 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
            <p class="font-semibold">Low stock</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-amber-900/95">
                @foreach ($lowStockItems as $item)
                    <li>
                        {{ $item->name }}
                        — {{ (int) $item->stock_quantity }} on hand (reorder at {{ (int) $item->reorder_level }})
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="crm-panel p-5">
        <form method="GET" action="{{ route('inventory.index') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                <input
                    name="search"
                    value="{{ $search }}"
                    placeholder="Filter by name"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
            @if ($search !== '')
                <a href="{{ route('inventory.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">Name</th>
                        <th class="py-2 pr-3 font-medium">Category</th>
                        <th class="py-2 pr-3 font-medium">Track stock</th>
                        <th class="py-2 pr-3 font-medium">On hand</th>
                        <th class="py-2 pr-3 font-medium">Reorder at</th>
                        <th class="py-2 pr-3 font-medium">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $isLow = $item->track_inventory && (int) $item->stock_quantity <= (int) $item->reorder_level;
                        @endphp
                        <tr class="border-b border-slate-100 {{ $isLow ? 'bg-amber-50/60' : '' }}">
                            <td class="py-3 pr-3 font-medium">{{ $item->name }}</td>
                            <td class="py-3 pr-3">{{ $item->category ?: '—' }}</td>
                            <td class="py-3 pr-3">
                                @if ($item->track_inventory)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Yes</span>
                                @else
                                    <span class="text-xs text-slate-500">No</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3">
                                @if ($item->track_inventory)
                                    {{ (int) $item->stock_quantity }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3">
                                @if ($item->track_inventory)
                                    {{ (int) $item->reorder_level }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3">${{ number_format((float) $item->price, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">
                                No retail or tracked items yet. Mark categories as Product / Retail or enable “Track inventory” on a service.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
