<?php

namespace App\Modules\Estimation\Events;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An estimate was submitted for approval (spec E9); approvers are notified.
 */
class EstimateSubmitted
{
    use Dispatchable;

    public function __construct(public Estimate $estimate, public ?User $actor = null) {}
}
