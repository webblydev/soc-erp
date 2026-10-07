<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Notifications\ProjectManagerAssigned;

/**
 * Sets the PM, supervisor and support officer and puts them on the team (spec P6). Callers
 * authorize, validate and hold the transaction.
 */
class SyncProjectPeople
{
    /**
     * Column => team role.
     */
    private const ROLES = [
        'project_manager_id' => ProjectRole::PM,
        'supervisor_id' => ProjectRole::SUPERVISOR,
        'support_officer_id' => ProjectRole::SUPPORT_OFFICER,
    ];

    public function __construct(private AddTeamMember $addTeamMember) {}

    /**
     * @param  array<string, mixed>  $people
     */
    public function handle(User $actor, Project $project, array $people): void
    {
        $previousManager = $project->project_manager_id;

        foreach (self::ROLES as $column => $role) {
            if (array_key_exists($column, $people)) {
                $project->{$column} = $people[$column] !== null ? (int) $people[$column] : null;
            }
        }

        $project->save();

        foreach (self::ROLES as $column => $role) {
            $this->addTeamMember->ensureMember($actor, $project, $project->{$column}, $role, notify: $column !== 'project_manager_id');
        }

        if ($project->project_manager_id !== null && $project->project_manager_id !== $previousManager) {
            $user = Employee::query()->find($project->project_manager_id)?->user;

            if ($user !== null && $user->is_active && $user->id !== $actor->id) {
                $user->notify(new ProjectManagerAssigned($project));
            }
        }
    }
}
