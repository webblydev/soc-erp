<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gives a task to another employee (docs/04 §5.7 bulk "Reassign", spec P14).
 */
class ReassignTask
{
    public function __construct(private CreateTask $createTask) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, mixed $employeeId): void
    {
        Gate::forUser($actor)->authorize('update', $task);
        Gate::forUser($actor)->authorize('projects.tasks.assign');

        $data = Validator::make(['assignee_employee_id' => $employeeId], [
            'assignee_employee_id' => ['required', new AssignableEmployee($task->assignee_employee_id)],
        ], [], ['assignee_employee_id' => __('assignee')])->validate();

        if ((int) $data['assignee_employee_id'] === $task->assignee_employee_id) {
            return;
        }

        DB::transaction(fn () => $task->forceFill(['assignee_employee_id' => (int) $data['assignee_employee_id'], 'updated_by' => $actor->id])->save());

        $this->createTask->notifyAssignee($actor, $task);
    }
}
