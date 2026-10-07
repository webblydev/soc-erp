<?php

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectApproval;

/**
 * Approvals follow their project's visibility (docs/04 §2, spec P18).
 */
class ProjectApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.approvals.view') && $user->can('projects.projects.view');
    }

    public function view(User $user, ProjectApproval $approval): bool
    {
        return $user->can('viewApprovals', $approval->project);
    }

    public function update(User $user, ProjectApproval $approval): bool
    {
        return $user->can('manageApprovals', $approval->project);
    }
}
