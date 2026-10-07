<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Services\FindingNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * DRAFT → SUBMITTED (docs/05 §6.3, spec E15); responsible employees hear about their findings.
 */
class SubmitInspection
{
    public function __construct(private FindingNotifier $notifier) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, SiteInspection $inspection): SiteInspection
    {
        Gate::forUser($actor)->authorize('update', $inspection);

        if (! $inspection->hasStatus(InspectionStatus::DRAFT)) {
            throw ValidationException::withMessages(['inspection' => __('Only a draft inspection can be submitted.')]);
        }

        DB::transaction(fn () => $inspection->forceFill(['inspection_status_id' => InspectionStatus::idFor(InspectionStatus::SUBMITTED)])->save());
        $inspection->unsetRelation('status');

        foreach ($inspection->findings()->get() as $finding) {
            $this->notifier->assigned($finding, $actor);
        }

        return $inspection;
    }
}
