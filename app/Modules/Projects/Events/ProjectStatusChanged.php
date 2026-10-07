<?php

namespace App\Modules\Projects\Events;

use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A project moved to another status (docs/04 §9). Dispatched inside the transaction.
 */
class ProjectStatusChanged
{
    use Dispatchable;

    public function __construct(public Project $project, public int $fromStatusId, public int $toStatusId) {}
}
