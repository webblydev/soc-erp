<?php

namespace App\Modules\Estimation\Services;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ProjectAccess;

/**
 * Estimation & Site visibility comes from the project (spec E4). "Management" decisions (approving
 * above the PM limit, verifying, closing) go to users who see every project, or to the project's PM.
 */
final class EstimationAccess
{
    public function __construct(private ProjectAccess $projects) {}

    public function canSeeProject(User $user, Project $project): bool
    {
        return Project::query()->whereKey($project->id)->visibleTo($user)->exists();
    }

    /**
     * Super admin or a user with projects.projects.view_all (management, spec E9).
     */
    public function isManagement(User $user): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission('projects.projects.view_all');
    }

    public function isProjectManager(User $user, Project $project): bool
    {
        return $this->projects->isManager($project, $user);
    }

    /**
     * Management, or the project's PM.
     */
    public function leads(User $user, Project $project): bool
    {
        return $this->isManagement($user) || $this->isProjectManager($user, $project);
    }
}
