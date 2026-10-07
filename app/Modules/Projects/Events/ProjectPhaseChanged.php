<?php

namespace App\Modules\Projects\Events;

use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A project entered another phase (docs/04 §9); PHASE schedule triggers listen (spec P12).
 */
class ProjectPhaseChanged
{
    use Dispatchable;

    public function __construct(public Project $project, public ?int $fromPhaseId, public ?int $toPhaseId) {}
}
