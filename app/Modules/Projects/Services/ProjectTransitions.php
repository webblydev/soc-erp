<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Allowed project status moves (docs/04 §6.1, spec P9). Statuses added on Master Data are open
 * statuses reachable from and to any open status. Reopening a closed project is separate.
 */
final class ProjectTransitions
{
    private const MAP = [
        ProjectStatus::ENQUIRY => [ProjectStatus::CONTRACTED, ProjectStatus::CANCELLED],
        ProjectStatus::CONTRACTED => [ProjectStatus::IN_PROGRESS, ProjectStatus::CANCELLED],
        ProjectStatus::IN_PROGRESS => [ProjectStatus::ON_HOLD, ProjectStatus::HANDED_OVER, ProjectStatus::CANCELLED],
        ProjectStatus::ON_HOLD => [ProjectStatus::IN_PROGRESS, ProjectStatus::CANCELLED],
        ProjectStatus::HANDED_OVER => [ProjectStatus::COMPLETED, ProjectStatus::CANCELLED],
    ];

    public function allows(ProjectStatus $from, ProjectStatus $to): bool
    {
        if ($from->is_closed || $from->id === $to->id) {
            return false;
        }

        $known = array_key_exists($from->code, self::MAP) || in_array($from->code, [ProjectStatus::COMPLETED, ProjectStatus::CANCELLED], true);
        $toKnown = array_key_exists($to->code, self::MAP) || in_array($to->code, [ProjectStatus::COMPLETED, ProjectStatus::CANCELLED], true);

        if (! $known || ! $toKnown) {
            return ! $to->is_closed;
        }

        return in_array($to->code, self::MAP[$from->code] ?? [], true);
    }

    /**
     * Statuses the project can move to from the given one, in sort order.
     *
     * @return Collection<int, ProjectStatus>
     */
    public function targets(ProjectStatus $from): Collection
    {
        return ProjectStatus::query()->active()->ordered()->get()->filter(fn (ProjectStatus $to): bool => $this->allows($from, $to))->values();
    }
}
