<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a draft inspection (spec E24).
 */
class DeleteInspection
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, SiteInspection $inspection): void
    {
        Gate::forUser($actor)->authorize('delete', $inspection);

        if (! $inspection->hasStatus(InspectionStatus::DRAFT)) {
            throw ValidationException::withMessages(['inspection' => __('Only a draft inspection can be deleted.')]);
        }

        DB::transaction(fn () => $inspection->delete());
    }
}
