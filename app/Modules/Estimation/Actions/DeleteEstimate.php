<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a draft or rejected estimate (spec E24). Revision 0 goes only while no later
 * revision exists.
 */
class DeleteEstimate
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Estimate $estimate): void
    {
        Gate::forUser($actor)->authorize('delete', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::DRAFT) && ! $estimate->hasStatus(EstimateStatus::REJECTED)) {
            throw ValidationException::withMessages(['estimate' => __('Only a draft or rejected estimate can be deleted.')]);
        }

        if ($estimate->revision_no === 0 && $estimate->revisions()->exists()) {
            throw ValidationException::withMessages(['estimate' => __('The original cannot be deleted while it has revisions.')]);
        }

        DB::transaction(fn () => $estimate->delete());
    }
}
