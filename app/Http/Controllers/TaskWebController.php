<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\AppointmentFormLookupCache;
use App\Support\CustomerTimeline;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskWebController extends Controller
{
    private const VIEWS = ['my_today', 'mine_pending', 'overdue', 'upcoming', 'all_pending'];

    public function index(Request $request): View
    {
        $view = (string) $request->query('view', 'my_today');
        if (! in_array($view, self::VIEWS, true)) {
            $view = 'my_today';
        }

        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId > 0 && ! Customer::query()->whereKey($customerId)->exists()) {
            $customerId = 0;
        }

        $clinicTz = AppointmentPolicyEnforcer::clinicTimezone();
        $appTz = (string) config('app.timezone');
        $todayKey = Carbon::now($clinicTz)->toDateString();
        [$todayStart, $todayEnd] = AppointmentPolicyEnforcer::clinicDayBounds($todayKey);

        $query = Task::query()
            ->with(['customer:id,first_name,last_name', 'assignedTo:id,name', 'opportunity:id,title', 'createdBy:id,name'])
            ->when($customerId > 0, fn ($q) => $q->where('customer_id', $customerId));

        $userId = (int) $request->user()->id;

        if ($view === 'my_today') {
            $query->where('assigned_to_user_id', $userId)
                ->where('status', Task::STATUS_PENDING)
                ->whereBetween('due_at', [$todayStart, $todayEnd])
                ->orderBy('due_at');
        } elseif ($view === 'mine_pending') {
            $query->where('assigned_to_user_id', $userId)
                ->where('status', Task::STATUS_PENDING)
                ->orderBy('due_at');
        } elseif ($view === 'overdue') {
            $query->where('status', Task::STATUS_PENDING)
                ->where('due_at', '<', $todayStart)
                ->orderBy('due_at');
        } elseif ($view === 'upcoming') {
            $query->where('status', Task::STATUS_PENDING)
                ->where('due_at', '>', $todayEnd)
                ->where('due_at', '<=', Carbon::now($appTz)->copy()->addDays(7))
                ->orderBy('due_at');
        } else {
            $query->where('status', Task::STATUS_PENDING)
                ->orderBy('due_at');
        }

        $tasks = $query->limit(200)->get();

        $filterCustomer = $customerId > 0 ? Customer::query()->find($customerId) : null;

        return view('tasks.index', [
            'title' => 'Tasks · BeautiSkin CRM',
            'tasks' => $tasks,
            'currentView' => $view,
            'viewLabels' => [
                'my_today' => 'My tasks today',
                'mine_pending' => 'My open tasks',
                'overdue' => 'Overdue',
                'upcoming' => 'Next 7 days',
                'all_pending' => 'All open tasks',
            ],
            'customers' => AppointmentFormLookupCache::customers(),
            'staffUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'opportunities' => Opportunity::query()
                ->with('customer:id,first_name,last_name')
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['id', 'customer_id', 'title', 'stage']),
            'customerIdFilter' => $customerId,
            'filterCustomer' => $filterCustomer,
            'clinicTimezone' => $clinicTz,
            'kindLabels' => Task::kindLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedTaskPayload($request, true);

        $task = Task::create([
            'customer_id' => (int) $validated['customer_id'],
            'opportunity_id' => $validated['opportunity_id'] ?? null,
            'assigned_to_user_id' => (int) $validated['assigned_to_user_id'],
            'created_by_user_id' => $request->user()->id,
            'kind' => $validated['kind'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'],
            'remind_at' => $validated['remind_at'] ?? null,
            'status' => Task::STATUS_PENDING,
        ]);

        $customer = Customer::query()->findOrFail($task->customer_id);
        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_TASK_CREATED,
            sprintf(
                'Task: %s (due %s)',
                $task->title,
                $task->due_at->timezone(AppointmentPolicyEnforcer::clinicTimezone())->format('M j, Y g:i A')
            ),
            $request->user()->id,
            $task->id,
        );

        return $this->redirectToTasksIndex($request, 'Task created.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $this->validatedTaskPayload($request, false);

        $status = $validated['status'];
        $completedAt = null;
        $completedBy = null;
        if ($status === Task::STATUS_COMPLETED) {
            $completedAt = $task->completed_at ?? now();
            $completedBy = $task->completed_by_user_id ?? $request->user()->id;
        }
        if ($status === Task::STATUS_CANCELLED) {
            $completedAt = null;
            $completedBy = null;
        }

        $previousStatus = $task->status;

        $task->update([
            'customer_id' => (int) $validated['customer_id'],
            'opportunity_id' => $validated['opportunity_id'] ?? null,
            'assigned_to_user_id' => (int) $validated['assigned_to_user_id'],
            'kind' => $validated['kind'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'],
            'remind_at' => $validated['remind_at'] ?? null,
            'status' => $status,
            'completed_at' => $completedAt,
            'completed_by_user_id' => $completedBy,
        ]);

        $customer = Customer::query()->findOrFail($task->customer_id);
        $task->refresh();

        if ($status === Task::STATUS_CANCELLED) {
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_TASK_CANCELLED,
                sprintf('Task cancelled: %s', $task->title),
                $request->user()->id,
                $task->id,
            );
        } elseif ($status === Task::STATUS_COMPLETED && $previousStatus !== Task::STATUS_COMPLETED) {
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_TASK_COMPLETED,
                sprintf('Completed task: %s', $task->title),
                $request->user()->id,
                $task->id,
            );
        } else {
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_TASK_UPDATED,
                sprintf('Task updated: %s', $task->title),
                $request->user()->id,
                $task->id,
            );
        }

        return $this->redirectToTasksIndex($request, 'Task updated.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        if ($task->status !== Task::STATUS_PENDING) {
            return $this->redirectToTasksIndex($request, 'Task is not open.');
        }

        $task->update([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by_user_id' => $request->user()->id,
        ]);

        $customer = Customer::query()->findOrFail($task->customer_id);
        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_TASK_COMPLETED,
            sprintf('Completed task: %s', $task->title),
            $request->user()->id,
            $task->id,
        );

        return $this->redirectToTasksIndex($request, 'Task marked complete.');
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        if ($task->status !== Task::STATUS_COMPLETED) {
            return $this->redirectToTasksIndex($request, 'Only completed tasks can be reopened.');
        }

        $task->update([
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
            'completed_by_user_id' => null,
        ]);

        $customer = Customer::query()->findOrFail($task->customer_id);
        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_TASK_UPDATED,
            sprintf('Reopened task: %s', $task->title),
            $request->user()->id,
            $task->id,
        );

        return $this->redirectToTasksIndex($request, 'Task reopened.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($task->customer_id);
        $title = $task->title;
        $taskId = $task->id;

        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_TASK_CANCELLED,
            sprintf('Task removed: %s', $title),
            $request->user()->id,
            $taskId,
        );

        $task->delete();

        return $this->redirectToTasksIndex($request, 'Task removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTaskPayload(Request $request, bool $isCreate): array
    {
        $kinds = array_keys(Task::kindLabels());
        $statuses = [Task::STATUS_PENDING, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED];

        $rules = [
            'customer_id' => ['required', 'exists:customers,id'],
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
            'assigned_to_user_id' => ['required', 'exists:users,id'],
            'kind' => ['required', 'string', Rule::in($kinds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['required', 'date'],
            'remind_at' => ['nullable', 'date', 'before:due_at'],
        ];

        if (! $isCreate) {
            $rules['status'] = ['required', 'string', Rule::in($statuses)];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['opportunity_id'])) {
            $opp = Opportunity::query()->find((int) $validated['opportunity_id']);
            if (! $opp || (int) $opp->customer_id !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages([
                    'opportunity_id' => 'Opportunity must belong to the selected customer.',
                ]);
            }
        }

        return $validated;
    }

    private function redirectToTasksIndex(Request $request, string $message): RedirectResponse
    {
        $view = (string) $request->input('return_view', $request->query('view', 'my_today'));
        if (! in_array($view, self::VIEWS, true)) {
            $view = 'my_today';
        }

        $cid = (int) $request->input('return_customer_id', $request->query('customer_id', 0));

        $query = array_filter([
            'view' => $view,
            'customer_id' => $cid > 0 ? $cid : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('tasks.index', $query)
            ->with('status', $message);
    }
}
