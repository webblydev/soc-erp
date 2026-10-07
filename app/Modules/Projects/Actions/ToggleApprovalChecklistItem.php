<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ApprovalChecklistItem;
use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ticks a document or step needed for an approval (docs/04 §3.10).
 */
class ToggleApprovalChecklistItem
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectApproval $approval, ApprovalChecklistItem $item, bool $done): void
    {
        Gate::forUser($actor)->authorize('update', $approval);

        if ($item->project_approval_id !== $approval->id) {
            throw ValidationException::withMessages(['checklist' => __('This item belongs to another approval.')]);
        }

        DB::transaction(fn () => $item->forceFill(['is_done' => $done, 'done_at' => $done ? now() : null])->save());
    }
}
