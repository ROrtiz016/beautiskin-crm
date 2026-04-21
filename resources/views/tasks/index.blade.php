@extends('layouts.app')

@php
    $returnView = $currentView;
    $returnCustomer = $customerIdFilter > 0 ? (string) $customerIdFilter : '';
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Tasks</h1>
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                Follow-ups with owners and due dates. Queues use the clinic timezone (<span class="font-medium">{{ $clinicTimezone }}</span>) for &ldquo;today&rdquo; boundaries.
            </p>
        </div>
        <button type="button" class="crm-btn-primary text-sm shrink-0" onclick="openCreateTaskModal()">+ New task</button>
    </div>

    @if ($filterCustomer)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-pink-200 bg-pink-50/80 px-4 py-3 text-sm text-pink-950">
            <p>
                Filtered to
                <a href="{{ route('customers.show', $filterCustomer) }}" class="font-semibold underline">{{ $filterCustomer->first_name }} {{ $filterCustomer->last_name }}</a>
            </p>
            <a href="{{ route('tasks.index', ['view' => $currentView]) }}" class="text-xs font-semibold text-pink-800 hover:text-pink-900">Clear customer filter</a>
        </div>
    @endif

    <section class="mb-6 crm-panel p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Queues</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($viewLabels as $key => $label)
                @php
                    $q = array_filter(['view' => $key, 'customer_id' => $customerIdFilter ?: null]);
                @endphp
                <a
                    href="{{ route('tasks.index', $q) }}"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ $currentView === $key ? 'border-pink-500 bg-pink-50 text-pink-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="crm-panel p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-3 font-semibold">Due</th>
                        <th class="py-2 pr-3 font-semibold">Task</th>
                        <th class="py-2 pr-3 font-semibold">Customer</th>
                        <th class="py-2 pr-3 font-semibold">Assignee</th>
                        <th class="py-2 pr-3 font-semibold">Kind</th>
                        <th class="py-2 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-3 whitespace-nowrap text-slate-700">
                                {{ $task->due_at->timezone($clinicTimezone)->format('M j, Y g:i A') }}
                                @if ($task->remind_at)
                                    <p class="text-[11px] text-slate-500">Remind {{ $task->remind_at->timezone($clinicTimezone)->format('M j g:i A') }}</p>
                                @endif
                            </td>
                            <td class="py-2 pr-3">
                                <p class="font-medium text-slate-900">{{ $task->title }}</p>
                                @if ($task->opportunity)
                                    <p class="text-xs text-slate-500">Deal: {{ $task->opportunity->title }}</p>
                                @endif
                            </td>
                            <td class="py-2 pr-3">
                                <a href="{{ route('customers.show', $task->customer) }}" class="font-medium text-pink-700 hover:text-pink-800">
                                    {{ $task->customer?->first_name }} {{ $task->customer?->last_name }}
                                </a>
                            </td>
                            <td class="py-2 pr-3 text-slate-600">{{ $task->assignedTo?->name ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-slate-600">{{ $kindLabels[$task->kind] ?? $task->kind }}</td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2">
                                    @if ($task->status === \App\Models\Task::STATUS_PENDING)
                                        <form method="POST" action="{{ route('tasks.complete', $task) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="return_view" value="{{ $returnView }}">
                                            <input type="hidden" name="return_customer_id" value="{{ $returnCustomer }}">
                                            <button type="submit" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Complete</button>
                                        </form>
                                    @elseif ($task->status === \App\Models\Task::STATUS_COMPLETED)
                                        <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="return_view" value="{{ $returnView }}">
                                            <input type="hidden" name="return_customer_id" value="{{ $returnCustomer }}">
                                            <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reopen</button>
                                        </form>
                                    @endif
                                    @php
                                        $editPayload = [
                                            'update_url' => route('tasks.update', $task),
                                            'customer_id' => $task->customer_id,
                                            'opportunity_id' => $task->opportunity_id,
                                            'assigned_to_user_id' => $task->assigned_to_user_id,
                                            'kind' => $task->kind,
                                            'title' => $task->title,
                                            'description' => $task->description,
                                            'due_at' => $task->due_at->format('Y-m-d\TH:i'),
                                            'remind_at' => $task->remind_at?->format('Y-m-d\TH:i'),
                                            'status' => $task->status,
                                        ];
                                    @endphp
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        onclick="openEditTaskModal(this)"
                                        data-payload='@json($editPayload)'
                                    >
                                        Edit
                                    </button>
                                    @if ($task->status === \App\Models\Task::STATUS_PENDING)
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline" onsubmit="return confirm('Remove this task?');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="return_view" value="{{ $returnView }}">
                                            <input type="hidden" name="return_customer_id" value="{{ $returnCustomer }}">
                                            <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">No tasks in this queue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Create task --}}
    <div id="createTaskModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">New task</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeCreateTaskModal()">✕</button>
            </div>
            <form method="POST" action="{{ route('tasks.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="form_type" value="task_create">
                <input type="hidden" name="return_view" value="{{ $returnView }}">
                <input type="hidden" name="return_customer_id" value="{{ $returnCustomer }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select id="create_task_customer_id" name="customer_id" class="crm-input" required onchange="filterTaskOpportunities('create')">
                        <option value="">Select customer</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected((int) $customerIdFilter === (int) $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Related opportunity (optional)</label>
                    <select id="create_task_opportunity_id" name="opportunity_id" class="crm-input">
                        <option value="">None</option>
                        @foreach ($opportunities as $o)
                            <option value="{{ $o->id }}" data-customer-id="{{ $o->customer_id }}">
                                {{ $o->customer?->first_name }} {{ $o->customer?->last_name }} — {{ $o->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('opportunity_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Assign to</label>
                    <select name="assigned_to_user_id" class="crm-input" required>
                        @foreach ($staffUsers as $u)
                            <option value="{{ $u->id }}" @selected((int) old('assigned_to_user_id', auth()->id()) === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to_user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Kind</label>
                    <select name="kind" class="crm-input" required>
                        @foreach ($kindLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('kind', \App\Models\Task::KIND_FOLLOW_UP) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Title</label>
                    <input name="title" type="text" class="crm-input" required value="{{ old('title') }}" placeholder="e.g. Call back about membership">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Details</label>
                    <textarea name="description" rows="3" class="crm-input" placeholder="Context for the assignee">{{ old('description') }}</textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Due</label>
                        <input name="due_at" type="datetime-local" class="crm-input" required value="{{ old('due_at') }}">
                        @error('due_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Remind at (optional)</label>
                        <input name="remind_at" type="datetime-local" class="crm-input" value="{{ old('remind_at') }}">
                        @error('remind_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeCreateTaskModal()">Cancel</button>
                    <button type="submit" class="crm-btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit task --}}
    <div id="editTaskModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm">
        <div class="crm-modal max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit task</h2>
                <button type="button" class="text-slate-500 hover:text-slate-800" onclick="closeEditTaskModal()">✕</button>
            </div>
            <form id="editTaskForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_view" value="{{ $returnView }}">
                <input type="hidden" name="return_customer_id" value="{{ $returnCustomer }}">
                <div>
                    <label class="mb-1 block text-sm font-medium">Customer</label>
                    <select id="edit_task_customer_id" name="customer_id" class="crm-input" required onchange="filterTaskOpportunities('edit')">
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->first_name }} {{ $c->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Related opportunity (optional)</label>
                    <select id="edit_task_opportunity_id" name="opportunity_id" class="crm-input">
                        <option value="">None</option>
                        @foreach ($opportunities as $o)
                            <option value="{{ $o->id }}" data-customer-id="{{ $o->customer_id }}">
                                {{ $o->customer?->first_name }} {{ $o->customer?->last_name }} — {{ $o->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Assign to</label>
                    <select id="edit_task_assigned_to" name="assigned_to_user_id" class="crm-input" required>
                        @foreach ($staffUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Kind</label>
                    <select id="edit_task_kind" name="kind" class="crm-input" required>
                        @foreach ($kindLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Title</label>
                    <input id="edit_task_title" name="title" type="text" class="crm-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Details</label>
                    <textarea id="edit_task_description" name="description" rows="3" class="crm-input"></textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Due</label>
                        <input id="edit_task_due_at" name="due_at" type="datetime-local" class="crm-input" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Remind at (optional)</label>
                        <input id="edit_task_remind_at" name="remind_at" type="datetime-local" class="crm-input">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Status</label>
                    <select id="edit_task_status" name="status" class="crm-input" required>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="crm-btn-secondary" onclick="closeEditTaskModal()">Close</button>
                    <button type="submit" class="crm-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const createModal = document.getElementById('createTaskModal');
        const editModal = document.getElementById('editTaskModal');
        const editForm = document.getElementById('editTaskForm');

        function openCreateTaskModal() {
            createModal.classList.remove('hidden');
            createModal.classList.add('flex');
            filterTaskOpportunities('create');
        }
        function closeCreateTaskModal() {
            createModal.classList.add('hidden');
            createModal.classList.remove('flex');
        }
        function openEditTaskModal(btn) {
            let p = {};
            try {
                p = JSON.parse(btn.getAttribute('data-payload') || '{}');
            } catch {
                p = {};
            }
            editForm.action = p.update_url || '';
            document.getElementById('edit_task_customer_id').value = String(p.customer_id || '');
            document.getElementById('edit_task_opportunity_id').value = p.opportunity_id ? String(p.opportunity_id) : '';
            filterTaskOpportunities('edit');
            if (p.opportunity_id) {
                document.getElementById('edit_task_opportunity_id').value = String(p.opportunity_id);
            }
            document.getElementById('edit_task_assigned_to').value = String(p.assigned_to_user_id || '');
            document.getElementById('edit_task_kind').value = p.kind || 'general';
            document.getElementById('edit_task_title').value = p.title || '';
            document.getElementById('edit_task_description').value = p.description || '';
            document.getElementById('edit_task_due_at').value = p.due_at || '';
            document.getElementById('edit_task_remind_at').value = p.remind_at || '';
            document.getElementById('edit_task_status').value = p.status || 'pending';
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
        }
        function closeEditTaskModal() {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }
        function filterTaskOpportunities(mode) {
            const custSel = document.getElementById(mode === 'create' ? 'create_task_customer_id' : 'edit_task_customer_id');
            const oppSel = document.getElementById(mode === 'create' ? 'create_task_opportunity_id' : 'edit_task_opportunity_id');
            if (!custSel || !oppSel) return;
            const cid = custSel.value;
            const prev = oppSel.value;
            Array.from(oppSel.options).forEach((opt) => {
                if (!opt.value) {
                    opt.hidden = false;
                    return;
                }
                const match = !cid || opt.getAttribute('data-customer-id') === cid;
                opt.hidden = !match;
            });
            const selectedOpt = oppSel.selectedOptions[0];
            if (selectedOpt && selectedOpt.hidden) {
                oppSel.value = '';
            } else if (prev && !cid) {
                oppSel.value = prev;
            }
        }
        @if ($errors->any() && old('form_type') === 'task_create')
            openCreateTaskModal();
        @endif
    </script>
@endsection
