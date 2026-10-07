<?php

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ProjectAccess;

/**
 * Single-project access (docs/04 §2, spec P7). Super admins pass through Gate::before, so
 * Actions also check the project's state (signed contract, status) themselves.
 */
class ProjectPolicy
{
    public function __construct(private ProjectAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('projects.projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return Project::query()->whereKey($project->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('projects.projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.projects.update') && $this->view($user, $project);
    }

    public function changeStatus(User $user, Project $project): bool
    {
        return $user->can('projects.projects.change_status') && $this->view($user, $project);
    }

    /**
     * Complete a project despite open completion checks (spec P9).
     */
    public function close(User $user, Project $project): bool
    {
        return $user->can('projects.projects.close') && $this->view($user, $project);
    }

    public function reopen(User $user, Project $project): bool
    {
        return $user->can('projects.projects.reopen') && $this->view($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.projects.delete') && $this->view($user, $project);
    }

    public function viewContract(User $user, Project $project): bool
    {
        return ($user->can('projects.contracts.view') || $user->can('projects.contracts.manage')) && $this->view($user, $project);
    }

    public function manageContract(User $user, Project $project): bool
    {
        return $user->can('projects.contracts.manage') && $this->view($user, $project);
    }

    /**
     * A project manager manages the team of their own projects only (spec P13).
     */
    public function manageTeam(User $user, Project $project): bool
    {
        return $user->can('projects.team.manage') && $this->view($user, $project)
            && ($user->can('projects.projects.view_all') || $this->access->isManager($project, $user));
    }

    public function viewApprovals(User $user, Project $project): bool
    {
        return $user->can('projects.approvals.view') && $this->view($user, $project);
    }

    public function manageApprovals(User $user, Project $project): bool
    {
        return $user->can('projects.approvals.manage') && $this->view($user, $project);
    }

    public function createTask(User $user, Project $project): bool
    {
        return $user->can('projects.tasks.create') && $this->view($user, $project);
    }
}
