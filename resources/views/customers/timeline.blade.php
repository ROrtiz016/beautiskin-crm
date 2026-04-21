@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Timeline</h1>
            <p class="mt-1 text-sm text-slate-600">
                <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-pink-700 hover:text-pink-800">
                    {{ $customer->first_name }} {{ $customer->last_name }}
                </a>
                — unified activity for this customer.
            </p>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="crm-btn-secondary shrink-0 text-sm">← Profile</a>
    </div>

    @include('partials.activity-feed-filters', [
        'action' => route('customers.timeline.show', $customer),
        'clearUrl' => route('customers.timeline.show', $customer),
        'categoryLabels' => $categoryLabels,
        'showCustomerField' => false,
        'filterFormId' => 'customer-timeline-filters-'.$customer->id,
    ])

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold">Add note</h2>
            <p class="mt-1 text-xs text-slate-500">Saved to this customer&rsquo;s timeline.</p>
            <form method="POST" action="{{ route('customers.timeline-notes.store', $customer) }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="return_to" value="timeline">
                <textarea name="summary" rows="3" class="crm-input" placeholder="Outcome, internal note, or next step…" required>{{ old('summary') }}</textarea>
                @error('summary') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="crm-btn-primary text-sm">Save note</button>
            </form>
        </section>
        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold">Log call / email / SMS</h2>
            <p class="mt-1 text-xs text-slate-500">Manual log for outreach not synced from email or phone systems.</p>
            <form method="POST" action="{{ route('customers.communications.store', $customer) }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="return_to" value="timeline">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Channel</label>
                    <select name="channel" class="crm-input text-sm" required>
                        <option value="call" @selected(old('channel') === 'call')>Phone call</option>
                        <option value="email" @selected(old('channel') === 'email')>Email</option>
                        <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                    </select>
                </div>
                <textarea name="summary" rows="3" class="crm-input" placeholder="What was discussed or sent…" required>{{ old('summary') }}</textarea>
                @error('channel') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('summary') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="crm-btn-primary text-sm">Log communication</button>
            </form>
        </section>
    </div>

    <section class="crm-panel mb-6 p-5">
        <h2 class="text-lg font-semibold">Send using template</h2>
        <p class="mt-1 text-xs text-slate-500">
            Uses messaging templates from the admin control board. Email uses your configured mailer; SMS requires Twilio env vars (<code class="rounded bg-slate-100 px-1">TWILIO_ACCOUNT_SID</code>, <code class="rounded bg-slate-100 px-1">TWILIO_AUTH_TOKEN</code>, <code class="rounded bg-slate-100 px-1">TWILIO_FROM_NUMBER</code>).
        </p>
        <form method="POST" action="{{ route('customers.communications.templated', $customer) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            @csrf
            <input type="hidden" name="return_to" value="timeline">
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Template</label>
                <select name="template" class="crm-input text-sm" required>
                    <option value="follow_up" @selected(old('template') === 'follow_up')>Follow-up</option>
                    <option value="no_show" @selected(old('template') === 'no_show')>We missed you (no-show)</option>
                    <option value="reminder" @selected(old('template') === 'reminder')>Appointment reminder</option>
                </select>
                @error('template') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Channel</label>
                <select name="channel" class="crm-input text-sm" required>
                    <option value="email" @selected(old('channel', 'email') === 'email')>Email</option>
                    <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                </select>
                @error('channel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Appointment (optional — required for &ldquo;reminder&rdquo;)</label>
                <select name="appointment_id" class="crm-input text-sm">
                    <option value="">— None —</option>
                    @foreach ($recentAppointments as $appt)
                        <option value="{{ $appt->id }}" @selected((string) old('appointment_id') === (string) $appt->id)>
                            {{ optional($appt->scheduled_at)->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'TBD' }}
                        </option>
                    @endforeach
                </select>
                @error('appointment_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="submit" class="crm-btn-primary text-sm">Send message</button>
            </div>
        </form>
    </section>

    <section class="crm-panel p-5">
        <h2 class="text-lg font-semibold text-slate-900">Activity</h2>
        <p class="mt-1 text-xs text-slate-500">Newest first.</p>
        @include('partials.activity-feed-list', [
            'activities' => $activities,
            'categoryLabels' => $categoryLabels,
            'showCustomer' => false,
        ])
    </section>
@endsection
