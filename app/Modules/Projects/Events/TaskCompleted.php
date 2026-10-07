<?php

namespace App\Modules\Projects\Events;

use App\Modules\Projects\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task moved to DONE (spec P15); TASK schedule triggers listen (spec P12).
 */
class TaskCompleted
{
    use Dispatchable;

    public function __construct(public Task $task) {}
}
