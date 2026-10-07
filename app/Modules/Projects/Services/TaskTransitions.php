<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Models\TaskStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Allowed task status moves (docs/04 §6.2, spec P15). Statuses added on Master Data behave like
 * IN_PROGRESS.
 */
final class TaskTransitions
{
    private const MAP = [
        TaskStatus::TODO => [TaskStatus::IN_PROGRESS, TaskStatus::BLOCKED, TaskStatus::DONE, TaskStatus::CANCELLED],
        TaskStatus::IN_PROGRESS => [TaskStatus::REVIEW, TaskStatus::BLOCKED, TaskStatus::DONE, TaskStatus::CANCELLED],
        TaskStatus::REVIEW => [TaskStatus::IN_PROGRESS, TaskStatus::DONE, TaskStatus::CANCELLED],
        TaskStatus::BLOCKED => [TaskStatus::IN_PROGRESS, TaskStatus::CANCELLED],
        TaskStatus::DONE => [TaskStatus::IN_PROGRESS],
        TaskStatus::CANCELLED => [TaskStatus::TODO],
    ];

    public function allows(TaskStatus $from, TaskStatus $to): bool
    {
        if ($from->id === $to->id) {
            return false;
        }

        $fromCode = array_key_exists($from->code, self::MAP) ? $from->code : TaskStatus::IN_PROGRESS;

        if (! array_key_exists($to->code, self::MAP)) {
            return in_array($fromCode, [TaskStatus::TODO, TaskStatus::IN_PROGRESS, TaskStatus::REVIEW, TaskStatus::BLOCKED], true);
        }

        return in_array($to->code, self::MAP[$fromCode], true);
    }

    /**
     * Statuses the task can move to from the given one, in sort order.
     *
     * @return Collection<int, TaskStatus>
     */
    public function targets(TaskStatus $from): Collection
    {
        return TaskStatus::query()->active()->ordered()->get()->filter(fn (TaskStatus $to): bool => $this->allows($from, $to))->values();
    }
}
