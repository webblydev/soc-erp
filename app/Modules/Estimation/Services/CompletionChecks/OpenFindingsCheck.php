<?php

namespace App\Modules\Estimation\Services\CompletionChecks;

use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Projects\Contracts\ProjectCompletionCheck;
use App\Modules\Projects\Models\Project;

/**
 * High and critical site findings must be closed before completion (spec E22).
 */
class OpenFindingsCheck implements ProjectCompletionCheck
{
    public function check(Project $project): array
    {
        $open = SiteInspectionFinding::query()->where('project_id', $project->id)->open()->serious()->count();

        return $open === 0 ? [] : [trans_choice(':count high or critical site finding is still open.|:count high or critical site findings are still open.', $open)];
    }
}
