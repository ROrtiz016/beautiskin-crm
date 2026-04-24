<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Opportunity;
use App\Models\Task;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\CustomerTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
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

    private function taskWithRelations(Task $task): Task
    {
        return $task->fresh()->load([
            'customer:id,first_name,last_name',
            'assignedTo:id,name',
            'opportunity:id,title',
            'createdBy:id,name',
        ]);
    }

    public function store(Request $request): JsonResponse
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

        return response()->json([
            'message' => 'Task created.',
            'task' => $this->taskWithRelations($task),
        ], 201);
    }

    public function update(Request $request, Task $task): JsonResponse
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

        return response()->json([
            'message' => 'Task updated.',
            'task' => $this->taskWithRelations($task),
        ]);
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        if ($task->status !== Task::STATUS_PENDING) {
            return response()->json(['message' => 'Task is not open.'], 422);
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

        return response()->json([
            'message' => 'Task marked complete.',
            'task' => $this->taskWithRelations($task),
        ]);
    }

    public function reopen(Request $request, Task $task): JsonResponse
    {
        if ($task->status !== Task::STATUS_COMPLETED) {
            return response()->json(['message' => 'Only completed tasks can be reopened.'], 422);
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

        return response()->json([
            'message' => 'Task reopened.',
            'task' => $this->taskWithRelations($task),
        ]);
    }

    public function destroy(Request $request, Task $task): Response
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

        return response()->noContent();
    }
}
