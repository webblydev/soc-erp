<?php

namespace App\Modules\Projects\Jobs;

use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Archives tasks completed more than projects.archive_done_tasks_after_days ago (docs/04 §6.2).
 */
class ArchiveDoneTasks implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $days = (int) Settings::get('projects.archive_done_tasks_after_days', 30);

        if ($days <= 0) {
            return;
        }

        Task::query()->whereNull('archived_at')
            ->whereIn('task_status_id', TaskStatus::query()->select('id')->where('is_done', true))
            ->where('completed_at', '<', now()->subDays($days))
            ->update(['archived_at' => now()]);
    }
}
