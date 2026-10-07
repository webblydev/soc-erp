<?php

namespace App\Modules\Estimation\Policies;

use App\Models\User;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Services\EstimationAccess;

/**
 * Inspections follow their project's visibility (docs/05 §2, spec E4, E15).
 */
class SiteInspectionPolicy
{
    public function __construct(private EstimationAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('site.inspections.view') && $user->can('projects.projects.view');
    }

    public function view(User $user, SiteInspection $inspection): bool
    {
        return $this->allows($user, $inspection, 'view');
    }

    public function create(User $user): bool
    {
        return $user->can('site.inspections.create') && $user->can('projects.projects.view');
    }

    public function update(User $user, SiteInspection $inspection): bool
    {
        return $this->allows($user, $inspection, 'update');
    }

    /**
     * Close an inspection by hand: management or the PM (spec E15).
     */
    public function close(User $user, SiteInspection $inspection): bool
    {
        return $this->allows($user, $inspection, 'update') && $this->access->leads($user, $inspection->project);
    }

    public function delete(User $user, SiteInspection $inspection): bool
    {
        return $this->allows($user, $inspection, 'delete');
    }

    public function print(User $user, SiteInspection $inspection): bool
    {
        return $this->allows($user, $inspection, 'print');
    }

    private function allows(User $user, SiteInspection $inspection, string $action): bool
    {
        return $user->can('site.inspections.'.$action) && $this->access->canSeeProject($user, $inspection->project);
    }
}
