<?php

namespace App\Modules\Projects\Listeners;

use App\Modules\Projects\Events\ApprovalStatusChanged;
use App\Modules\Projects\Events\ProjectPhaseChanged;
use App\Modules\Projects\Events\TaskCompleted;
use App\Modules\Projects\Services\ScheduleTriggers;

/**
 * Re-checks a project's milestones when a phase, approval or task changes (spec P12).
 */
class EvaluateScheduleTriggers
{
    public function __construct(private ScheduleTriggers $triggers) {}

    public function handle(ProjectPhaseChanged|ApprovalStatusChanged|TaskCompleted $event): void
    {
        $project = match (true) {
            $event instanceof ProjectPhaseChanged => $event->project,
            $event instanceof ApprovalStatusChanged => $event->approval->project,
            default => $event->task->project,
        };

        if ($project !== null) {
            $this->triggers->evaluate($project->fresh() ?? $project);
        }
    }
}
