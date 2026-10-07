<?php

namespace App\Modules\Estimation\Listeners;

use App\Modules\Estimation\Actions\BuildBudgetFromEstimate;
use App\Modules\Estimation\Events\EstimateApproved;
use App\Modules\Estimation\Models\EstimateKind;
use App\Support\Facades\Settings;

/**
 * Rebuilds the budget from an approved BOQ or material estimate when
 * estimation.auto_budget_from_approved_estimate is on (docs/05 §6.1, spec E11). Runs inside the
 * approval transaction.
 */
class BuildBudgetOnApproval
{
    public function __construct(private BuildBudgetFromEstimate $build) {}

    public function handle(EstimateApproved $event): void
    {
        if (! (bool) Settings::get('estimation.auto_budget_from_approved_estimate', true)
            || ! in_array($event->estimate->kind->code, [EstimateKind::BOQ, EstimateKind::MATERIAL], true)) {
            return;
        }

        $this->build->handle($event->estimate, $event->actor, authorize: false);
    }
}
