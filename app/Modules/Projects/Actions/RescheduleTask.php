<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Sets a task's due date (docs/04 §5.7 bulk "Set due date", PRJ-BR-11).
 */
class RescheduleTask
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, string $dueDate): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        if (strtotime($dueDate) === false) {
            throw ValidationException::withMessages(['due_date' => __('Enter a valid date.')]);
        }

        if ($task->start_date !== null && $dueDate < $task->start_date->toDateString()) {
            throw ValidationException::withMessages(['due_date' => __('The due date cannot be before the start date.')]);
        }

        DB::transaction(fn () => $task->forceFill(['due_date' => $dueDate, 'updated_by' => $actor->id])->save());
    }
}
