<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Hands a project to another PM (docs/04 §5.1 bulk "Change PM", PRJ-BR-10).
 */
class ChangeProjectManager
{
    public function __construct(private SyncProjectPeople $syncPeople) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, mixed $employeeId): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        $data = Validator::make(['project_manager_id' => $employeeId], [
            'project_manager_id' => ['required', new AssignableEmployee($project->project_manager_id)],
        ], [], ['project_manager_id' => __('project manager')])->validate();

        DB::transaction(fn () => $this->syncPeople->handle($actor, $project, ['project_manager_id' => (int) $data['project_manager_id']]));
    }
}
