<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ChangesEstimateStatus;
use App\Modules\Estimation\Events\EstimateApproved;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Notifications\EstimateDecided;
use App\Modules\Estimation\Services\EstimateApprovers;
use App\Modules\Estimation\Services\EstimationAccess;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * SUBMITTED → APPROVED (docs/05 §6.1, spec E9). A PM approves up to estimation.pm_approval_limit
 * (ES-BR-04); the root's earlier approved revision becomes SUPERSEDED (ES-BR-02).
 */
class ApproveEstimate
{
    use ChangesEstimateStatus;

    public function __construct(private EstimateApprovers $approvers, private EstimationAccess $access) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Estimate $estimate): Estimate
    {
        Gate::forUser($actor)->authorize('approve', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::SUBMITTED)) {
            throw ValidationException::withMessages(['estimate' => __('Only a submitted estimate can be approved.')]);
        }

        if (! $this->access->isManagement($actor) && ! $this->approvers->isWithinPmLimit($estimate)) {
            throw ValidationException::withMessages(['estimate' => __('Estimates above :limit need management approval.', ['limit' => Money::format($this->approvers->limit())])]);
        }

        DB::transaction(function () use ($actor, $estimate): void {
            $approvedId = EstimateStatus::idFor(EstimateStatus::APPROVED);

            Estimate::query()->familyOf($estimate)->whereKeyNot($estimate->id)->where('estimate_status_id', $approvedId)->get()
                ->each(fn (Estimate $previous) => $this->moveTo($previous, EstimateStatus::SUPERSEDED, $actor, __('Superseded by :number.', ['number' => $estimate->estimate_number])));

            $this->moveTo($estimate, EstimateStatus::APPROVED, $actor, null, ['approved_by' => $actor->id, 'approved_at' => now()]);

            EstimateApproved::dispatch($estimate, $actor);
        });

        $preparer = $estimate->preparer->user;

        if ($preparer !== null && $preparer->is_active && ! $preparer->is($actor)) {
            $preparer->notify(new EstimateDecided($estimate, approved: true));
        }

        return $estimate;
    }
}
