<?php

namespace App\Modules\Projects\Services\CompletionChecks;

use App\Modules\Projects\Contracts\ProjectCompletionCheck;
use App\Modules\Projects\Models\Project;

/**
 * All approvals must be in a final status (PRJ-BR-08).
 */
class OpenApprovalsCheck implements ProjectCompletionCheck
{
    public function check(Project $project): array
    {
        $pending = $project->approvals()->pending()->count();

        return $pending === 0 ? [] : [trans_choice(':count approval is not final yet.|:count approvals are not final yet.', $pending)];
    }
}
