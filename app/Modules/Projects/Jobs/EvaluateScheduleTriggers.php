<?php

namespace App\Modules\Projects\Jobs;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Services\ScheduleTriggers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily pass over open projects with pending milestones (docs/04 §6.4, spec P12): DATE triggers
 * come due here, and any missed event-based trigger catches up.
 */
class EvaluateScheduleTriggers implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(ScheduleTriggers $triggers): void
    {
        Project::query()->open()
            ->whereHas('schedules', fn ($query) => $query->where('schedule_status_id', ScheduleStatus::idFor(ScheduleStatus::PENDING)))
            ->with('status', 'phase')
            ->chunkById(100, function (Collection $projects) use ($triggers): void {
                foreach ($projects as $project) {
                    DB::transaction(fn () => $triggers->evaluate($project));
                }
            });
    }
}
