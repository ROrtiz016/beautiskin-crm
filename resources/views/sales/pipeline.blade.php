@extends('layouts.app')

@php
    $pipelineCustomerHidden = $customerIdFilter > 0 ? (string) $customerIdFilter : '';
    $stageAccent = static function (string $stage): string {
        return match ($stage) {
            'new' => 'border-slate-200 bg-slate-50',
            'qualified' => 'border-blue-200 bg-blue-50/60',
            'proposal' => 'border-violet-200 bg-violet-50/60',
            'negotiation' => 'border-amber-200 bg-amber-50/60',
            default => 'border-slate-200 bg-slate-50',
        };
    };
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Sales pipeline</h1>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                Track deals by stage, expected close dates, and ownership. Mark <span class="font-medium">Lost</span> with a reason; reopen closed deals by editing the stage.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('view-sales')
                <a href="{{ route('sales.index') }}" class="crm-btn-secondary text-sm">Sales dashboard</a>
            @endcan
            <button type="button" class="crm-btn-primary text-sm" onclick="openCreateOpportunityModal()">+ New opportunity</button>
        </div>
    </div>

    @if ($filterCustomer)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-pink-200 bg-pink-50/80 px-4 py-3 text-sm text-pink-950">
            <p>
                Showing opportunities for
                <a href="{{ route('customers.show', $filterCustomer) }}" class="font-semibold underline">{{ $filterCustomer->first_name }} {{ $filterCustomer->last_name }}</a>
            </p>
            <a href="{{ route('sales.pipeline.index') }}" class="text-xs font-semibold text-pink-800 hover:text-pink-900">Clear filter</a>
        </div>
    @endif

    <section class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open pipeline value</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">${{ number_format($openPipelineValue, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sum of deal amounts in New through Negotiation.</p>
        </div>
        <div class="crm-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Closing in 30 days</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">${{ number_format($closingNext30Days, 2) }}</p>
            <p class="mt-1 text-xs text-slate-500">Open deals with an expected close from today through the next 30 days.</p>
        </div>
        <div class="crm-panel p-4 sm:col-span-2 lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open deals by stage</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($openStages as $st)
                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-800">
                        {{ $stageLabels[$st] }}
                        <span class="tabular-nums text-slate-500">{{ $countsOpen[$st] ?? 0 }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <div class="mb-4 overflow-x-auto pb-2">
        <div class="flex min-w-[64rem] gap-3">
            @foreach ($openStages as $stage)
                <div class="flex w-64 shrink-0 flex-col rounded-xl border border-slate-200 bg-white/90 shadow-sm">
                    <div class="border-b border-slate-200 px-3 py-2 {{ $stageAccent($stage) }} rounded-t-xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $stageLabels[$stage] }}</p>
                        <p class="text-[11px] text-slate-500">{{ $byStage->get($stage, collect())->count() }} deal(s)</p>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-2">
                        @foreach ($byStage->get($stage, collect()) as $opportunity)
                            @include('sales.partials.opportunity-card', [
                                'opportunity' => $opportunity,
                                'stageLabels' => $stageLabels,
                                'pipelineCustomerHidden' => $pipelineCustomerHidden,
                            ])
                        @endforeach
                        @if ($byStage->get($stage, collect())->isEmpty())
                            <p class="px-1 py-6 text-center text-xs text-slate-400">No deals</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($closedStages as $closed)
            <section class="crm-panel p-5">
                <h2 class="text-base font-semibold text-slate-900">{{ $stageLabels[$closed] }}</h2>
                <p class="mt-1 text-xs text-slate-500">Most recent first (same filters as above).</p>
                <div class="mt-4 space-y-2">
                    @forelse ($byStage->get($closed, collect()) as $opportunity)
                        @include('sales.partials.opportunity-card', [
                            'opportunity' => $opportunity,
                            'stageLabels' => $stageLabels,
                            'pipelineCustomerHidden' => $pipelineCustomerHidden,
                            'compact' => true,
                        ])
                    @empty
                        <p class="text-sm text-slate-500">No {{ strtolower($stageLabels[$closed]) }} deals yet.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{-- Create --}}
    <div id="createOpportunityModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">New opportunity</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreateOpportunityModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('sales.opportunities.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="create_opportunity">
                <input type="hidden" name="pipeline_customer_id" value="{{ $pipelineCustomerHidden }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select name="customer_id" class="crm-input" required>
                        <option value="">Select customer</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected((int) $customerIdFilter === (int) $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Title</label>
                    <input name="title" type="text" class="crm-input" required value="{{ old('title') }}" placeholder="e.g. Annual membership upgrade">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Amount (USD)</label>
                        <input name="amount" type="number" step="0.01" min="0" class="crm-input" value="{{ old('amount') }}">
                        @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Expected close</label>
                        <input name="expected_close_date" type="date" class="crm-input" value="{{ old('expected_close_date') }}">
                        @error('expected_close_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Owner (optional)</label>
                    <select name="owner_user_id" class="crm-input">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $u)
                            <option value="{{ $u->id }}" @selected((string) old('owner_user_id') === (string) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    @error('owner_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="3" class="crm-input">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeCreateOpportunityModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit --}}
    <div id="editOpportunityModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit opportunity</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditOpportunityModal()">✕</button>
            </div>
            <form id="editOpportunityForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="pipeline_customer_id" value="{{ $pipelineCustomerHidden }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select id="edit_opp_customer_id" name="customer_id" class="crm-input" required>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->first_name }} {{ $c->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Title</label>
                    <input id="edit_opp_title" name="title" type="text" class="crm-input" required>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Amount (USD)</label>
                        <input id="edit_opp_amount" name="amount" type="number" step="0.01" min="0" class="crm-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Expected close</label>
                        <input id="edit_opp_expected_close" name="expected_close_date" type="date" class="crm-input">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Stage</label>
                    <select id="edit_opp_stage" name="stage" class="crm-input" required>
                        @foreach (array_merge($openStages, $closedStages) as $st)
                            <option value="{{ $st }}">{{ $stageLabels[$st] }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="edit_opp_loss_wrap" class="hidden">
                    <label class="mb-1 block text-sm font-medium">Loss reason <span class="text-rose-600">*</span></label>
                    <textarea id="edit_opp_loss_reason" name="loss_reason" rows="3" class="crm-input" placeholder="Why was this deal lost?"></textarea>
                    @error('loss_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Owner (optional)</label>
                    <select id="edit_opp_owner_id" name="owner_user_id" class="crm-input">
                        <option value="">Unassigned</option>
                        @foreach ($staffUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="edit_opp_notes" name="notes" rows="3" class="crm-input"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                    <button type="button" class="crm-btn-secondary" onclick="closeEditOpportunityModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Save</button>
                </div>
            </form>
            <form id="deleteOpportunityForm" method="POST" action="" class="mt-3 border-t border-slate-200 pt-3" onsubmit="return confirm('Remove this opportunity from the pipeline?');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="pipeline_customer_id" value="{{ $pipelineCustomerHidden }}">
                <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-700">Delete opportunity</button>
            </form>
        </div>
    </div>

    {{-- Mark lost (quick stage) --}}
    <div id="markLostModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-md">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Mark as lost</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeMarkLostModal()">✕</button>
            </div>
            <form id="markLostForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="pipeline_customer_id" value="{{ $pipelineCustomerHidden }}">
                <input type="hidden" name="stage" value="lost">
                <div>
                    <label class="mb-1 block text-sm font-medium">Loss reason <span class="text-rose-600">*</span></label>
                    <textarea name="loss_reason" rows="4" class="crm-input" required placeholder="What happened?"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeMarkLostModal()">Cancel</button>
                    <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const lostStage = 'lost';
        const createModal = document.getElementById('createOpportunityModal');
        const editModal = document.getElementById('editOpportunityModal');
        const editForm = document.getElementById('editOpportunityForm');
        const deleteForm = document.getElementById('deleteOpportunityForm');
        const markLostModal = document.getElementById('markLostModal');
        const markLostForm = document.getElementById('markLostForm');
        let pendingStageSelect = null;
        let pendingStageOriginal = '';

        function openCreateOpportunityModal() {
            createModal.classList.remove('hidden');
            createModal.classList.add('flex');
        }
        function closeCreateOpportunityModal() {
            createModal.classList.add('hidden');
            createModal.classList.remove('flex');
        }
        function openEditOpportunityModal(btn) {
            let p = {};
            try {
                p = JSON.parse(btn.getAttribute('data-payload') || '{}');
            } catch {
                p = {};
            }
            const base = p.update_url || '';
            editForm.action = base;
            if (deleteForm) deleteForm.action = base;
            document.getElementById('edit_opp_customer_id').value = String(p.customer_id || '');
            document.getElementById('edit_opp_title').value = p.title || '';
            document.getElementById('edit_opp_amount').value = p.amount ?? '';
            document.getElementById('edit_opp_expected_close').value = p.expected_close || '';
            document.getElementById('edit_opp_stage').value = p.stage || 'new';
            document.getElementById('edit_opp_owner_id').value = p.owner_id != null ? String(p.owner_id) : '';
            document.getElementById('edit_opp_notes').value = p.notes || '';
            document.getElementById('edit_opp_loss_reason').value = p.loss_reason || '';
            toggleEditLossField();
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        }
        function closeEditOpportunityModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }
        document.getElementById('edit_opp_stage')?.addEventListener('change', toggleEditLossField);
        function toggleEditLossField() {
            const st = document.getElementById('edit_opp_stage')?.value;
            const wrap = document.getElementById('edit_opp_loss_wrap');
            if (!wrap) return;
            const show = st === lostStage;
            wrap.classList.toggle('hidden', !show);
            const ta = document.getElementById('edit_opp_loss_reason');
            if (ta) ta.required = show;
        }
        function openMarkLostModal(actionUrl) {
            markLostForm.action = actionUrl;
            markLostForm.querySelector('textarea[name="loss_reason"]').value = '';
            markLostModal.classList.remove('hidden');
            markLostModal.classList.add('flex');
        }
        function closeMarkLostModal() {
            markLostModal.classList.add('hidden');
            markLostModal.classList.remove('flex');
            if (pendingStageSelect) {
                pendingStageSelect.value = pendingStageOriginal;
                pendingStageSelect = null;
            }
        }
        function handleQuickStageChange(selectEl) {
            const val = selectEl.value;
            const original = selectEl.getAttribute('data-original-stage') || selectEl.dataset.originalStage || '';
            if (val === lostStage) {
                selectEl.value = original;
                pendingStageSelect = selectEl;
                pendingStageOriginal = original;
                const action = selectEl.closest('form')?.getAttribute('action');
                if (action) openMarkLostModal(action);
                return;
            }
            selectEl.closest('form')?.submit();
        }
        @if ($errors->any() && old('form_type') === 'create_opportunity')
            openCreateOpportunityModal();
        @endif
    </script>
@endsection
