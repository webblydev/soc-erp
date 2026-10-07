<?php

namespace App\Modules\Estimation\Events;

use App\Modules\Estimation\Models\SiteInspectionFinding;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A finding of a submitted inspection has a responsible employee (docs/05 §9, spec E15).
 */
class FindingAssigned
{
    use Dispatchable;

    public function __construct(public SiteInspectionFinding $finding) {}
}
