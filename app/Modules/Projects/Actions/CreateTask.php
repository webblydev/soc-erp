<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Concerns\ValidatesTaskInput;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use App\Modules\Projects\Notifications\TaskAssigned;
use App\Support\NumberSequenceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a task on a project or a general task (docs/04 §5.6, spec P14).
 */
class CreateTask
{
    use ValidatesTaskInput;

    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input): Task
    {
        Gate::forUser($actor)->authorize('create', Task::class);

        $input = $this->normaliseTask($input);
        $input['task_priority_id'] ??= TaskPriority::query()->where('code', TaskPriority::NORMAL)->value('id');
        $data = $this->validateTask($actor, $input, null);

        $task = DB::transaction(function () use ($actor, $data): Task {
            $task = new Task(Arr::only($data, (new Task)->getFillable()));
            $task->estimated_hours ??= TaskType::query()->whereKey((int) $data['task_type_id'])->value('default_estimated_hours');
            $task->forceFill([
                'task_number' => $this->numbers->next('task'),
                'project_id' => $data['project_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'task_type_id' => (int) $data['task_type_id'],
                'task_status_id' => TaskStatus::idFor(TaskStatus::TODO),
                'assigned_by' => $actor->id,
                'assignee_employee_id' => $data['assignee_employee_id'] ?? null,
                'support_officer_id' => $data['support_officer_id'] ?? null,
                'reviewer_employee_id' => $data['reviewer_employee_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->syncTaskExtras($task, $data);

            return $task;
        });

        $this->notifyAssignee($actor, $task);

        return $task->refresh();
    }

    public function notifyAssignee(User $actor, Task $task): void
    {
        $user = $task->assignee_employee_id !== null ? Employee::query()->find($task->assignee_employee_id)?->user : null;

        if ($user !== null && $user->is_active && $user->id !== $actor->id) {
            $user->notify(new TaskAssigned($task));
        }
    }
}
