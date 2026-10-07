<?php

namespace App\Modules\Estimation\Concerns;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;

/**
 * Moves an estimate to a status and writes its history row (spec E9). Callers hold the transaction.
 */
trait ChangesEstimateStatus
{
    /**
     * @param  array<string, mixed>  $attributes  other columns to set in the same save
     */
    protected function moveTo(Estimate $estimate, string $code, ?User $actor, ?string $note = null, array $attributes = []): void
    {
        $statusId = EstimateStatus::idFor($code);

        $estimate->forceFill(['estimate_status_id' => $statusId, ...$attributes])->save();
        $estimate->unsetRelation('status');

        $estimate->statusHistories()->create([
            'estimate_status_id' => $statusId,
            'note' => $note,
            'changed_by' => $actor?->id,
            'changed_at' => now(),
        ]);
    }
}
