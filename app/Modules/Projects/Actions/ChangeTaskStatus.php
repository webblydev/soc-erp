<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Events\TaskCompleted;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Notifications\TaskReviewRequested;
use App\Modules\Projects\Services\TaskTransitions;
use App\Support\Facades\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves a task through its life-cycle (docs/04 §6.2, spec P15, PRJ-BR-12, PRJ-BR-13). Returns
 * soft warnings (unfinished predecessors) for the UI to show.
 */
class ChangeTaskStatus
{
    public function __construct(private TaskTransitions $transitions) {}

    /**
     * @param  array{blocked_reason?: string|null, confirm_open_checklist?: bool}  $input
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, int $toStatusId, array $input = []): array
    {
        Gate::forUser($actor)->authorize('changeStatus', $task);

        $from = $task->status;
        $to = TaskStatus::query()->find($toStatusId);

        if ($to === null || ! $this->transitions->allows($from, $to)) {
            throw ValidationException::withMessages(['task_status_id' => __('A task cannot move from :from to :to.', ['from' => $from->name, 'to' => $to->name ?? '?'])]);
        }

        if ($from->code === TaskStatus::REVIEW && in_array($to->code, [TaskStatus::DONE, TaskStatus::IN_PROGRESS], true)) {
            Gate::forUser($actor)->authorize('review', $task);
        }

        $attributes = ['task_status_id' => $to->id, 'updated_by' => $actor->id];

        if ($to->code === TaskStatus::BLOCKED) {
            $reason = trim((string) ($input['blocked_reason'] ?? ''));

            if ($reason === '') {
                throw ValidationException::withMessages(['blocked_reason' => __('Say what is blocking the task.')]);
            }

            $attributes['blocked_reason'] = mb_substr($reason, 0, 255);
        } elseif ($from->code === TaskStatus::BLOCKED) {
            $attributes['blocked_reason'] = null;
        }

        if ($to->is_done) {
            $this->guardChecklist($task, (bool) ($input['confirm_open_checklist'] ?? false));
            $attributes += ['completed_at' => now(), 'completed_by' => $actor->id, 'progress_pct' => 100];
        } elseif ($from->is_done) {
            $attributes += ['completed_at' => null, 'completed_by' => null, 'archived_at' => null];
        }

        $warnings = $to->code === TaskStatus::IN_PROGRESS && $from->code === TaskStatus::TODO ? $this->predecessorWarnings($task) : [];

        DB::transaction(function () use ($task, $attributes, $to): void {
            $task->forceFill($attributes)->save();
            $task->unsetRelation('status');

            if ($to->is_done) {
                TaskCompleted::dispatch($task);
            }

            if ($task->project !== null) {
                $this->refreshProjectCompletion($task->project);
            }
        });

        if ($to->code === TaskStatus::REVIEW) {
            $this->requestReview($actor, $task);
        }

        return $warnings;
    }

    /**
     * PRJ-BR-13: open checklist items need confirmation, or block when the setting says so.
     *
     * @throws ValidationException
     */
    private function guardChecklist(Task $task, bool $confirmed): void
    {
        $open = $task->checklist()->where('is_done', false)->count();

        if ($open === 0) {
            return;
        }

        if (Settings::get('projects.block_complete_with_open_checklist', false)) {
            throw ValidationException::withMessages(['checklist' => trans_choice('Tick the last checklist item first.|Tick the :count open checklist items first.', $open)]);
        }

        if (! $confirmed) {
            throw ValidationException::withMessages(['confirm_open_checklist' => trans_choice(':count checklist item is still open. Complete anyway?|:count checklist items are still open. Complete anyway?', $open)]);
        }
    }

    /**
     * @return list<string>
     */
    private function predecessorWarnings(Task $task): array
    {
        return $task->predecessors()->with('status')->get()
            ->reject(fn (Task $predecessor): bool => (bool) $predecessor->status->is_done)
            ->map(fn (Task $predecessor): string => __(':number :title is not done yet.', ['number' => $predecessor->task_number, 'title' => $predecessor->title]))
            ->values()->all();
    }

    /**
     * projects.completion_from_tasks: completion % = done / non-cancelled tasks.
     */
    private function refreshProjectCompletion(Project $project): void
    {
        if (! Settings::get('projects.completion_from_tasks', false)) {
            return;
        }

        $counted = $project->tasks()->whereHas('status', fn ($query) => $query->where('is_cancelled', false));
        $total = (clone $counted)->count();
        $done = (clone $counted)->whereHas('status', fn ($query) => $query->where('is_done', true))->count();

        $project->forceFill(['completion_pct' => $total === 0 ? 0 : (string) BigDecimal::of($done * 100)->dividedBy($total, 2, RoundingMode::HalfUp)])->save();
    }

    private function requestReview(User $actor, Task $task): void
    {
        $employeeId = $task->reviewer_employee_id ?? $task->project?->project_manager_id;
        $user = $employeeId !== null ? Employee::query()->find($employeeId)?->user : null;

        if ($user !== null && $user->is_active && $user->id !== $actor->id) {
            $user->notify(new TaskReviewRequested($task));
        }
    }
}
