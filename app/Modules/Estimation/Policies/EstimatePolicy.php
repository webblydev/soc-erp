<?php

namespace App\Modules\Estimation\Policies;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Services\EstimationAccess;

/**
 * Estimates follow their project's visibility (docs/05 §2, spec E4). Status rules live in the actions.
 */
class EstimatePolicy
{
    public function __construct(private EstimationAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('estimation.estimates.view') && $user->can('projects.projects.view');
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return $user->can('estimation.estimates.view') && $this->access->canSeeProject($user, $estimate->project);
    }

    public function create(User $user): bool
    {
        return $user->can('estimation.estimates.create') && $user->can('projects.projects.view');
    }

    public function update(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'update');
    }

    public function submit(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'submit');
    }

    /**
     * Management approves any estimate; a PM only on their projects, within the limit checked by
     * ApproveEstimate (ES-BR-04).
     */
    public function approve(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'approve') && $this->access->leads($user, $estimate->project);
    }

    public function revise(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'revise');
    }

    public function delete(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'delete');
    }

    public function print(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'print');
    }

    public function export(User $user, Estimate $estimate): bool
    {
        return $this->allows($user, $estimate, 'export');
    }

    private function allows(User $user, Estimate $estimate, string $action): bool
    {
        return $user->can('estimation.estimates.'.$action) && $this->access->canSeeProject($user, $estimate->project);
    }
}
