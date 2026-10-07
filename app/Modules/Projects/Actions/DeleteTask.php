<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Soft-deletes a task with its sub-tasks (spec P24).
 */
class DeleteTask
{
    public function handle(User $actor, Task $task): void
    {
        Gate::forUser($actor)->authorize('delete', $task);

        DB::transaction(function () use ($task): void {
            $task->subtasks()->get()->each->delete();
            $task->delete();
        });
    }
}
