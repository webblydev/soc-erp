<?php

namespace App\Modules\Projects\Events;

use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A project was created (docs/00 §9). Dispatched inside the creating transaction.
 */
class ProjectCreated
{
    use Dispatchable;

    public function __construct(public Project $project) {}
}
