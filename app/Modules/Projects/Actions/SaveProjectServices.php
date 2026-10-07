<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ServiceLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a project's service lines and its contract value (PRJ-BR-03). A signed contract
 * locks them; changes then go through an amendment (PRJ-BR-04, spec P5).
 */
class SaveProjectServices
{
    public function __construct(private ServiceLines $serviceLines) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, mixed $lines): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        if ($project->contract()->with('status')->first()?->isSigned()) {
            throw ValidationException::withMessages(['services' => __('The contract is signed. Change services through an amendment.')]);
        }

        $validated = $this->serviceLines->validate($lines, $project, $project->services()->pluck('service_id')->all());

        DB::transaction(fn () => $this->serviceLines->apply($project, $validated));
    }
}
