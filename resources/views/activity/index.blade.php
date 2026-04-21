@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Activity</h1>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                One chronological feed of notes, tasks, appointments, payments, communications, and pipeline updates across customers.
            </p>
        </div>
        <a href="{{ route('customers.index') }}" class="crm-btn-secondary shrink-0 text-sm">Customers</a>
    </div>

    @include('partials.activity-feed-filters', [
        'action' => route('activity.index'),
        'clearUrl' => route('activity.index'),
        'categoryLabels' => $categoryLabels,
        'showCustomerField' => true,
        'filterFormId' => 'activity-global-filters',
    ])

    <section class="crm-panel p-5">
        <h2 class="text-lg font-semibold text-slate-900">Timeline</h2>
        <p class="mt-1 text-xs text-slate-500">Newest first.</p>
        @include('partials.activity-feed-list', [
            'activities' => $activities,
            'categoryLabels' => $categoryLabels,
            'showCustomer' => true,
        ])
    </section>
@endsection
