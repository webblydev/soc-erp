<?php

namespace App\Modules\Projects\Services\CompletionChecks;

use App\Modules\Projects\Contracts\ProjectCompletionCheck;
use App\Modules\Projects\Models\Project;

/**
 * All tasks must be done or cancelled (PRJ-BR-08).
 */
class OpenTasksCheck implements ProjectCompletionCheck
{
    public function check(Project $project): array
    {
        $open = $project->tasks()->open()->count();

        return $open === 0 ? [] : [trans_choice(':count task is still open.|:count tasks are still open.', $open)];
    }
}
