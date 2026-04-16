@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Operations</h1>
            <p class="mt-1 text-sm text-slate-600">Today’s snapshot, waitlist, staff load, and booking policy.</p>
        </div>
        <a href="{{ route('admin.control-board') }}" class="text-sm font-semibold text-pink-700 hover:text-pink-800">← Admin control board</a>
    </div>

    <p class="mb-4 text-xs text-slate-500">
        <span class="font-semibold text-slate-600">Tip:</span> Drag any panel by the handle on the right to reorder. Your layout is saved for this account.
    </p>

    <div
        id="operations-dashboard-sortable"
        class="flex flex-col gap-8"
        data-sortable-dashboard="operations"
        data-save-dashboard-url="{{ route('user.dashboard-layout.update') }}"
        data-initial-order='@json($operationsPanelOrder)'
    >
        @foreach ($operationsPanelOrder as $panelId)
            <div data-dashboard-panel="{{ $panelId }}" class="relative">
                @if ($panelId === 'ops-kpis')
                    @include('admin.operations.panels.kpis')
                @elseif ($panelId === 'ops-staff')
                    @include('admin.operations.panels.staff')
                @elseif ($panelId === 'ops-settings')
                    @include('admin.operations.panels.settings')
                @endif
                @include('admin.partials.dashboard-drag-handle')
            </div>
        @endforeach
    </div>
@endsection
