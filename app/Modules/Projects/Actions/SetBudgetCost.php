<?php

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\Project;

/**
 * Stores the project's budget cost, the total of its budget lines (docs/04 §3.2). Called by
 * Estimation inside its own transaction whenever the budget changes (docs/05 §3.6).
 */
class SetBudgetCost
{
    public function handle(Project $project, string $total): void
    {
        $project->forceFill(['budget_cost' => $total])->save();
    }
}
