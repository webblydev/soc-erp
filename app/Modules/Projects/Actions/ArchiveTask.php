<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Archives a finished task or brings it back (docs/04 §6.2). Archived tasks leave the default views.
 */
class ArchiveTask
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, bool $archive = true): void
    {
        Gate::forUser($actor)->authorize('archive', $task);

        if ($archive && $task->isOpen()) {
            throw ValidationException::withMessages(['task' => __('Only completed or cancelled tasks can be archived.')]);
        }

        DB::transaction(fn () => $task->forceFill(['archived_at' => $archive ? now() : null])->save());
    }
}
