@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><a href="{{ route('quotes.index') }}" class="text-pink-700 hover:text-pink-800">Quotes</a> / #{{ $quote->id }}</p>
            <h1 class="text-2xl font-bold">{{ $quote->title ?: 'Quote #'.$quote->id }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $quote->customer?->first_name }} {{ $quote->customer?->last_name }}
                @if ($quote->customer?->email)
                    · {{ $quote->customer->email }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-800">{{ str_replace('_', ' ', $quote->status) }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="crm-panel p-5 lg:col-span-2">
            <h2 class="text-lg font-semibold">Line items</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="py-2 pr-2 font-medium">Description</th>
                            <th class="py-2 pr-2 font-medium">Type</th>
                            <th class="py-2 pr-2 font-medium">Qty</th>
                            <th class="py-2 pr-2 font-medium text-right">Line</th>
                            <th class="py-2 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($quote->lines as $line)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-2">{{ $line->label }}</td>
                                <td class="py-2 pr-2 capitalize">{{ $line->line_kind }}</td>
                                <td class="py-2 pr-2">{{ (int) $line->quantity }}</td>
                                <td class="py-2 pr-2 text-right">${{ number_format((float) $line->line_total, 2) }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('quote-lines.destroy', $line) }}" class="inline" onsubmit="return confirm('Remove this line?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500">No lines yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-4 border-t border-slate-200 pt-6 md:grid-cols-3">
                <form method="POST" action="{{ route('quotes.lines.store', $quote) }}" class="rounded-lg border border-slate-200 p-3">
                    @csrf
                    <input type="hidden" name="line_kind" value="service">
                    <h3 class="text-xs font-semibold uppercase text-slate-500">Add service</h3>
                    <select name="service_id" class="crm-input mt-2 text-sm" required>
                        <option value="">Select…</option>
                        @foreach ($services as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} — ${{ number_format((float) $s->price, 2) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="quantity" min="1" value="1" class="crm-input mt-2 text-sm" required>
                    <button type="submit" class="crm-btn-primary mt-2 w-full text-sm py-2">Add</button>
                </form>
                <form method="POST" action="{{ route('quotes.lines.store', $quote) }}" class="rounded-lg border border-slate-200 p-3">
                    @csrf
                    <input type="hidden" name="line_kind" value="package">
                    <h3 class="text-xs font-semibold uppercase text-slate-500">Add package</h3>
                    <select name="treatment_package_id" class="crm-input mt-2 text-sm" required>
                        <option value="">Select…</option>
                        @foreach ($packages as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} — ${{ number_format((float) $p->package_price, 2) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="quantity" min="1" value="1" class="crm-input mt-2 text-sm" required>
                    <button type="submit" class="crm-btn-primary mt-2 w-full text-sm py-2">Add</button>
                </form>
                <form method="POST" action="{{ route('quotes.lines.store', $quote) }}" class="rounded-lg border border-slate-200 p-3">
                    @csrf
                    <input type="hidden" name="line_kind" value="custom">
                    <h3 class="text-xs font-semibold uppercase text-slate-500">Custom line</h3>
                    <input name="label" class="crm-input mt-2 text-sm" placeholder="Description" required>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input name="unit_price" type="number" step="0.01" min="0" class="crm-input text-sm" placeholder="Price" required>
                        <input name="quantity" type="number" min="1" value="1" class="crm-input text-sm" required>
                    </div>
                    <button type="submit" class="crm-btn-primary mt-2 w-full text-sm py-2">Add</button>
                </form>
            </div>
        </section>

        <div class="space-y-6">
            <section class="crm-panel p-5">
                <h2 class="text-lg font-semibold">Totals</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal (lines)</dt><dd class="font-medium">${{ number_format((float) $quote->subtotal_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium">-${{ number_format((float) $quote->discount_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="font-medium">${{ number_format((float) $quote->tax_amount, 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><dt class="font-semibold">Quote total</dt><dd class="font-bold text-pink-800">${{ number_format((float) $quote->total_amount, 2) }}</dd></div>
                </dl>
                <form method="POST" action="{{ route('quotes.update', $quote) }}" class="mt-4 space-y-2 border-t border-slate-200 pt-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Title</label>
                        <input name="title" value="{{ old('title', $quote->title) }}" class="crm-input text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Valid until</label>
                        <input name="valid_until" type="date" value="{{ old('valid_until', optional($quote->valid_until)->format('Y-m-d')) }}" class="crm-input text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Discount $</label>
                            <input name="discount_amount" type="number" step="0.01" min="0" value="{{ old('discount_amount', $quote->discount_amount) }}" class="crm-input text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Tax $</label>
                            <input name="tax_amount" type="number" step="0.01" min="0" value="{{ old('tax_amount', $quote->tax_amount) }}" class="crm-input text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Notes</label>
                        <textarea name="notes" rows="2" class="crm-input text-sm">{{ old('notes', $quote->notes) }}</textarea>
                    </div>
                    <button type="submit" class="crm-btn-secondary w-full text-sm py-2">Save header</button>
                </form>
            </section>

            <section class="crm-panel p-5">
                <h2 class="text-lg font-semibold">Status</h2>
                <form method="POST" action="{{ route('quotes.status.update', $quote) }}" class="mt-3 space-y-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="crm-input text-sm">
                        @foreach ([\App\Models\Quote::STATUS_DRAFT, \App\Models\Quote::STATUS_SENT, \App\Models\Quote::STATUS_ACCEPTED, \App\Models\Quote::STATUS_DECLINED, \App\Models\Quote::STATUS_EXPIRED] as $st)
                            <option value="{{ $st }}" @selected($quote->status === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="crm-btn-primary w-full text-sm py-2">Update status</button>
                </form>
            </section>

            <section class="crm-panel p-5">
                <h2 class="text-lg font-semibold">Link to visit</h2>
                <p class="mt-1 text-xs text-slate-500">Tie this quote to an appointment so staff can reconcile quoted total vs. visit lines and deposits.</p>
                <form method="POST" action="{{ route('quotes.link-appointment', $quote) }}" class="mt-3 space-y-2">
                    @csrf
                    <select name="appointment_id" class="crm-input text-sm" required>
                        <option value="">Select appointment…</option>
                        @foreach ($linkableAppointments as $appt)
                            <option value="{{ $appt->id }}">
                                #{{ $appt->id }} · {{ optional($appt->scheduled_at)->format('M j, Y g:i A') }} · {{ ucfirst(str_replace('_', ' ', $appt->status)) }}
                                @if ((int) $appt->quote_id === (int) $quote->id)
                                    (linked)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="crm-btn-secondary w-full text-sm py-2">Link quote</button>
                </form>
            </section>
        </div>
    </div>
@endsection
