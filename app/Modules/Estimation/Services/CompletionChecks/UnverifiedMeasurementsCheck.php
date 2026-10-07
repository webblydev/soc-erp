<?php

namespace App\Modules\Estimation\Services\CompletionChecks;

use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Projects\Contracts\ProjectCompletionCheck;
use App\Modules\Projects\Models\Project;

/**
 * Recorded MB entries must be verified or rejected before completion (spec E22).
 */
class UnverifiedMeasurementsCheck implements ProjectCompletionCheck
{
    public function check(Project $project): array
    {
        $waiting = $project->measurementEntries()->whereHas('status', fn ($query) => $query->where('code', MbStatus::RECORDED))->count();

        return $waiting === 0 ? [] : [trans_choice(':count measurement is not verified yet.|:count measurements are not verified yet.', $waiting)];
    }
}
