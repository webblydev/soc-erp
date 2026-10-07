<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskTimeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Logs the actor's hours on a task; actual hours are the Σ of the logs (spec P16).
 */
class LogTaskTime
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, array $input): TaskTimeLog
    {
        Gate::forUser($actor)->authorize('logTime', $task);

        $data = Validator::make($input, [
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['work_date' => __('work date')])->validate();

        return DB::transaction(function () use ($actor, $task, $data): TaskTimeLog {
            $log = $task->timeLogs()->create([...$data, 'employee_id' => $actor->employee_id]);
            $task->forceFill(['actual_hours' => (string) $task->timeLogs()->sum('hours')])->save();

            return $log;
        });
    }
}
