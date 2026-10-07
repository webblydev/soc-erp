<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ticks or unticks a task checklist item (docs/04 §3.9).
 */
class ToggleChecklistItem
{
    public function handle(User $actor, TaskChecklistItem $item, bool $done): void
    {
        Gate::forUser($actor)->authorize('changeStatus', $item->task);

        DB::transaction(fn () => $item->forceFill([
            'is_done' => $done,
            'done_by' => $done ? $actor->id : null,
            'done_at' => $done ? now() : null,
        ])->save());
    }
}
