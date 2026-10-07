<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Notifications\MilestoneDue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Moves PENDING milestones whose trigger is satisfied to DUE and tells the PM and accounts
 * (docs/04 §6.4, spec P12, PRJ-AC-04). Runs inside the caller's transaction.
 */
final class ScheduleTriggers
{
    /**
     * @return list<PaymentSchedule> the lines that became due
     */
    public function evaluate(Project $project): array
    {
        if ($project->status->is_closed) {
            return [];
        }

        $moved = [];
        $pending = $project->schedules()->with('trigger')->where('schedule_status_id', ScheduleStatus::idFor(ScheduleStatus::PENDING))->get();

        foreach ($pending as $line) {
            if ($this->satisfied($project, $line)) {
                $this->markDue($line);
                $moved[] = $line;
            }
        }

        return $moved;
    }

    public function markDue(PaymentSchedule $line): void
    {
        $line->forceFill(['schedule_status_id' => ScheduleStatus::idFor(ScheduleStatus::DUE), 'due_at' => now()])->save();

        Notification::send($this->recipients($line->project), new MilestoneDue($line));
    }

    private function satisfied(Project $project, PaymentSchedule $line): bool
    {
        $ref = $line->trigger_ref_id;

        return match ($line->trigger->code) {
            ScheduleTrigger::DATE => $line->due_date !== null && $line->due_date->lte(today()),
            ScheduleTrigger::PHASE => $ref !== null && $project->phase !== null
                && $project->phase->sort_order >= (int) ProjectPhase::query()->whereKey($ref)->value('sort_order'),
            ScheduleTrigger::APPROVAL => $ref !== null && $project->approvals()->whereKey($ref)
                ->where('approval_status_id', ApprovalStatus::idFor(ApprovalStatus::APPROVED))->exists(),
            ScheduleTrigger::TASK => $ref !== null && $project->tasks()->whereKey($ref)
                ->whereHas('status', fn ($query) => $query->where('is_done', true))->exists(),
            default => false,
        };
    }

    /**
     * The PM's login and active accountants / finance managers.
     *
     * @return Collection<int, User>
     */
    private function recipients(Project $project): Collection
    {
        $pmUserId = $project->manager?->user?->id;

        return User::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereHas('roles', fn ($query) => $query->whereIn('code', ['accountant', 'finance_manager']))
                ->when($pmUserId !== null, fn ($query) => $query->orWhere('id', $pmUserId)))
            ->get();
    }
}
