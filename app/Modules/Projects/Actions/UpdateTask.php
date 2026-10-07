<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Concerns\ValidatesTaskInput;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a task (docs/04 §5.6, spec P14). Status changes go through ChangeTaskStatus.
 */
class UpdateTask
{
    use ValidatesTaskInput;

    public function __construct(private CreateTask $createTask) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, array $input): Task
    {
        Gate::forUser($actor)->authorize('update', $task);

        $data = $this->validateTask($actor, $this->normaliseTask($input), $task);
        $previousAssignee = $task->assignee_employee_id;

        DB::transaction(function () use ($actor, $task, $data): void {
            $task->fill(Arr::only($data, $task->getFillable()));
            $task->forceFill([
                'project_id' => $data['project_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'task_type_id' => (int) $data['task_type_id'],
                'assignee_employee_id' => $data['assignee_employee_id'] ?? null,
                'support_officer_id' => $data['support_officer_id'] ?? null,
                'reviewer_employee_id' => $data['reviewer_employee_id'] ?? null,
                'updated_by' => $actor->id,
            ])->save();

            Task::query()->where('parent_id', $task->id)->update(['project_id' => $task->project_id]);
            $this->syncTaskExtras($task, $data);
        });

        if ($task->assignee_employee_id !== $previousAssignee) {
            $this->createTask->notifyAssignee($actor, $task);
        }

        return $task->refresh();
    }
}
