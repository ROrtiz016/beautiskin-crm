@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Quotes</h1>
            <p class="mt-1 text-sm text-slate-600">Formal quotes with line items, package pricing, and totals before the visit is completed.</p>
        </div>
        <a href="{{ route('packages.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
            Manage packages
        </a>
    </div>

    <section class="crm-panel mb-6 p-5">
        <h2 class="text-sm font-semibold text-slate-800">New quote</h2>
        <form method="POST" action="{{ route('quotes.store') }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</label>
                <select name="customer_id" class="crm-input text-sm" required>
                    <option value="">Select…</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                    @endforeach
                </select>
                @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Title (optional)</label>
                <input name="title" value="{{ old('title') }}" class="crm-input text-sm" placeholder="e.g. Bridal package">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="crm-btn-primary text-sm">Create draft</button>
        </form>
    </section>

    <section class="crm-panel p-5">
        <form method="GET" action="{{ route('quotes.index') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search customer</label>
                <input name="search" value="{{ $search }}" placeholder="Name or email" class="crm-input text-sm">
            </div>
            <div class="sm:w-56">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Filter by customer</label>
                <select name="customer_id" class="crm-input text-sm">
                    <option value="0">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((string) $customerId === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Apply</button>
            @if ($search !== '' || $customerId > 0)
                <a href="{{ route('quotes.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2 pr-3 font-medium">#</th>
                        <th class="py-2 pr-3 font-medium">Customer</th>
                        <th class="py-2 pr-3 font-medium">Title</th>
                        <th class="py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 pr-3 font-medium">Total</th>
                        <th class="py-2 pr-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quotes as $quote)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3 font-mono text-xs">{{ $quote->id }}</td>
                            <td class="py-3 pr-3">{{ $quote->customer?->first_name }} {{ $quote->customer?->last_name }}</td>
                            <td class="py-3 pr-3">{{ $quote->title ?: '—' }}</td>
                            <td class="py-3 pr-3 capitalize">{{ str_replace('_', ' ', $quote->status) }}</td>
                            <td class="py-3 pr-3">${{ number_format((float) $quote->total_amount, 2) }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('quotes.show', $quote) }}" class="font-semibold text-pink-700 hover:text-pink-800">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">No quotes yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $quotes->links() }}
        </div>
    </section>
@endsection
