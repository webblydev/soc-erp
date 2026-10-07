<?php

namespace App\Modules\Estimation\Contracts;

use App\Modules\Projects\Models\Project;

/**
 * A module that knows a project's committed or actual costs per cost category: open work orders
 * (07) and posted direct costs (08). Registered on BudgetCostSources (spec E2, E12).
 */
interface BudgetCostSource
{
    /**
     * Committed, not yet billed amounts keyed by cost category id.
     *
     * @return array<int, string>
     */
    public function committed(Project $project): array;

    /**
     * Actual (posted) amounts keyed by cost category id.
     *
     * @return array<int, string>
     */
    public function actual(Project $project): array;
}
