<?php

namespace App\Modules\Estimation\Events;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An estimate was rejected with a note (spec E9).
 */
class EstimateRejected
{
    use Dispatchable;

    public function __construct(public Estimate $estimate, public ?User $actor = null) {}
}
