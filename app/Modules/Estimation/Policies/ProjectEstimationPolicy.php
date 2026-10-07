<?php

namespace App\Modules\Estimation\Policies;

use App\Models\User;
use App\Modules\Estimation\Services\EstimationAccess;
use App\Modules\Projects\Models\Project;

/**
 * Estimation & Site abilities on a project, registered as named gates because Project has its own
 * policy (spec E4, E11).
 */
class ProjectEstimationPolicy
{
    /**
     * Gate names; each maps to the method of the same name.
     */
    public const ABILITIES = ['viewEstimates', 'createEstimate', 'viewBudget', 'manageBudget', 'viewSite', 'recordMeasurement', 'editMeasurementRate', 'createInspection'];

    public function __construct(private EstimationAccess $access) {}

    public function viewEstimates(User $user, Project $project): bool
    {
        return $user->can('estimation.estimates.view') && $this->access->canSeeProject($user, $project);
    }

    public function createEstimate(User $user, Project $project): bool
    {
        return $user->can('estimation.estimates.create') && $this->access->canSeeProject($user, $project);
    }

    public function viewBudget(User $user, Project $project): bool
    {
        return $user->can('estimation.budget.view') && $this->access->canSeeProject($user, $project);
    }

    /**
     * A PM manages the budget of their own projects only (spec E11).
     */
    public function manageBudget(User $user, Project $project): bool
    {
        return $user->can('estimation.budget.manage') && $this->access->canSeeProject($user, $project)
            && $this->access->leads($user, $project);
    }

    public function viewSite(User $user, Project $project): bool
    {
        return ($user->can('site.mb.view') || $user->can('site.inspections.view')) && $this->access->canSeeProject($user, $project);
    }

    public function recordMeasurement(User $user, Project $project): bool
    {
        return $user->can('site.mb.create') && $this->access->canSeeProject($user, $project);
    }

    /**
     * Change the default MB rate (spec E13).
     */
    public function editMeasurementRate(User $user, Project $project): bool
    {
        return $user->can('site.mb.edit_rate') && $this->access->canSeeProject($user, $project);
    }

    public function createInspection(User $user, Project $project): bool
    {
        return $user->can('site.inspections.create') && $this->access->canSeeProject($user, $project);
    }
}
