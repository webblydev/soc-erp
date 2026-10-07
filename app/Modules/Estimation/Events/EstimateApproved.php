<?php

namespace App\Modules\Estimation\Events;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An estimate was approved (spec E9); the budget listener rebuilds the budget (spec E11).
 */
class EstimateApproved
{
    use Dispatchable;

    public function __construct(public Estimate $estimate, public ?User $actor = null) {}
}
