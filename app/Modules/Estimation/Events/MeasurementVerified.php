<?php

namespace App\Modules\Estimation\Events;

use App\Modules\Estimation\Models\MeasurementEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An MB entry became VERIFIED and can be billed (docs/05 §8, spec E14); for running bills (06 / 07).
 */
class MeasurementVerified
{
    use Dispatchable;

    public function __construct(public MeasurementEntry $entry) {}
}
