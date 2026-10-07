<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ValidatesInspectionInput;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Services\FindingNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a finding to an inspection that is not closed (spec E15). On a submitted inspection the
 * responsible employee is told at once.
 */
class AddFinding
{
    use ValidatesInspectionInput;

    public function __construct(private FindingNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, SiteInspection $inspection, array $input): SiteInspectionFinding
    {
        Gate::forUser($actor)->authorize('update', $inspection);

        if ($inspection->hasStatus(InspectionStatus::CLOSED)) {
            throw ValidationException::withMessages(['inspection' => __('A closed inspection takes no new findings.')]);
        }

        $row = $this->validateFindings($inspection->project, [$input], key: 'finding')[0];
        unset($row['id']);

        $finding = DB::transaction(function () use ($inspection, $row): SiteInspectionFinding {
            $finding = $inspection->findings()->make([...$row, 'sort_order' => (int) $inspection->findings()->max('sort_order') + 1]);
            $finding->forceFill(['project_id' => $inspection->project_id, 'finding_status_id' => FindingStatus::idFor(FindingStatus::OPEN)])->save();

            return $finding;
        });

        if ($inspection->hasStatus(InspectionStatus::SUBMITTED)) {
            $this->notifier->assigned($finding, $actor);
        }

        return $finding;
    }
}
