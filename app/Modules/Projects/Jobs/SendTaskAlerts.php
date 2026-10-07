<?php

namespace App\Modules\Projects\Jobs;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Notifications\TaskDueTomorrow;
use App\Modules\Projects\Notifications\TasksOverdue;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Daily task alerts (docs/04 §10, spec P19): due tomorrow per task to the assignee, and one
 * overdue summary per user. A cache key per date stops a rerun from sending twice.
 */
class SendTaskAlerts implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        if (! Cache::add('projects:task-alerts:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        Task::query()->open()->whereNull('archived_at')->whereDate('due_date', today()->addDay())
            ->whereNotNull('assignee_employee_id')->with('assignee.user')
            ->each(function (Task $task): void {
                $user = $task->assignee?->user;

                if ($user !== null && $user->is_active) {
                    $user->notify(new TaskDueTomorrow($task));
                }
            });

        if (! Settings::get('projects.task_overdue_notify', true)) {
            return;
        }

        $assigned = Task::query()->overdue()->whereNull('archived_at')->whereNotNull('assignee_employee_id')
            ->selectRaw('assignee_employee_id as employee_id, count(*) as total')->groupBy('assignee_employee_id')->pluck('total', 'employee_id');
        $managed = Task::query()->overdue()->whereNull('archived_at')->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereNotNull('projects.project_manager_id')
            ->selectRaw('projects.project_manager_id as employee_id, count(*) as total')->groupBy('projects.project_manager_id')->pluck('total', 'employee_id');

        $employeeIds = $assigned->keys()->merge($managed->keys())->unique()->all();

        User::query()->where('is_active', true)->whereIn('employee_id', $employeeIds)->each(function (User $user) use ($assigned, $managed): void {
            $user->notify(new TasksOverdue((int) ($assigned[$user->employee_id] ?? 0), (int) ($managed[$user->employee_id] ?? 0)));
        });
    }
}
