<?php

namespace App\Modules\Projects\Events;

use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An approval event changed the approval's status (docs/04 §9, spec P18).
 */
class ApprovalStatusChanged
{
    use Dispatchable;

    public function __construct(public ProjectApproval $approval, public int $fromStatusId, public int $toStatusId) {}
}
