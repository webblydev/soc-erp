<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes an empty enquiry (PRJ-BR-17, spec P24).
 */
class DeleteProject
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project): void
    {
        Gate::forUser($actor)->authorize('delete', $project);

        if ($project->status->code !== ProjectStatus::ENQUIRY || $project->contract()->exists()
            || $project->tasks()->exists() || $project->approvals()->exists()) {
            throw ValidationException::withMessages(['project' => __('Only an enquiry with no contract, tasks or approvals can be deleted.')]);
        }

        DB::transaction(fn () => $project->delete());
    }
}
