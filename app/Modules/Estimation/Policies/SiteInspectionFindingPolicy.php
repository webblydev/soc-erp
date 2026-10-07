<?php

namespace App\Modules\Estimation\Policies;

use App\Models\User;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Services\EstimationAccess;

/**
 * Findings follow their inspection (spec E16).
 */
class SiteInspectionFindingPolicy
{
    public function __construct(private EstimationAccess $access) {}

    public function view(User $user, SiteInspectionFinding $finding): bool
    {
        return $user->can('site.inspections.view') && $this->access->canSeeProject($user, $finding->project);
    }

    /**
     * Move a finding on: management, the PM, or the responsible employee.
     */
    public function changeStatus(User $user, SiteInspectionFinding $finding): bool
    {
        if (! $user->can('site.inspections.close_finding') || ! $this->access->canSeeProject($user, $finding->project)) {
            return false;
        }

        return $this->access->leads($user, $finding->project)
            || ($user->employee_id !== null && $finding->responsibleEmployeeId() === $user->employee_id);
    }

    /**
     * Reopen a closed finding: management or the PM.
     */
    public function reopen(User $user, SiteInspectionFinding $finding): bool
    {
        return $user->can('site.inspections.close_finding') && $this->access->canSeeProject($user, $finding->project)
            && $this->access->leads($user, $finding->project);
    }
}
