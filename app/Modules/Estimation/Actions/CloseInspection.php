<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * SUBMITTED → CLOSED by the PM or management (docs/05 §6.3, spec E15). Open HIGH / CRITICAL
 * findings keep it open (ES-BR-12).
 */
class CloseInspection
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, SiteInspection $inspection): SiteInspection
    {
        Gate::forUser($actor)->authorize('close', $inspection);

        if (! $inspection->hasStatus(InspectionStatus::SUBMITTED)) {
            throw ValidationException::withMessages(['inspection' => __('Only a submitted inspection can be closed.')]);
        }

        $serious = $inspection->findings()->open()->serious()->count();

        if ($serious > 0) {
            throw ValidationException::withMessages(['inspection' => trans_choice('{1} One high or critical finding is still open (ES-BR-12).|[2,*] :count high or critical findings are still open (ES-BR-12).', $serious)]);
        }

        DB::transaction(fn () => $inspection->forceFill(['inspection_status_id' => InspectionStatus::idFor(InspectionStatus::CLOSED)])->save());
        $inspection->unsetRelation('status');

        return $inspection;
    }
}
