<?php

namespace App\Modules\Estimation\Policies;

use App\Models\User;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Services\EstimationAccess;

/**
 * MB entries follow their project's visibility (docs/05 §2, spec E4, E14).
 */
class MeasurementEntryPolicy
{
    public function __construct(private EstimationAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('site.mb.view') && $user->can('projects.projects.view');
    }

    public function view(User $user, MeasurementEntry $entry): bool
    {
        return $this->allows($user, $entry, 'view');
    }

    public function create(User $user): bool
    {
        return $user->can('site.mb.create') && $user->can('projects.projects.view');
    }

    public function update(User $user, MeasurementEntry $entry): bool
    {
        return $this->allows($user, $entry, 'update');
    }

    /**
     * Verify, reject or unverify: management, or the PM on their projects (spec E14).
     */
    public function verify(User $user, MeasurementEntry $entry): bool
    {
        return $this->allows($user, $entry, 'verify') && $this->access->leads($user, $entry->project);
    }

    public function delete(User $user, MeasurementEntry $entry): bool
    {
        return $this->allows($user, $entry, 'delete');
    }

    private function allows(User $user, MeasurementEntry $entry, string $action): bool
    {
        return $user->can('site.mb.'.$action) && $this->access->canSeeProject($user, $entry->project);
    }
}
