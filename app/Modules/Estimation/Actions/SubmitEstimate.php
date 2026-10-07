<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ChangesEstimateStatus;
use App\Modules\Estimation\Events\EstimateSubmitted;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Notifications\EstimateAwaitingApproval;
use App\Modules\Estimation\Services\EstimateApprovers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * DRAFT → SUBMITTED (docs/05 §6.1, spec E9). The estimate needs at least one line; the approvers
 * get estimation.estimates.submitted.
 */
class SubmitEstimate
{
    use ChangesEstimateStatus;

    public function __construct(private EstimateApprovers $approvers) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Estimate $estimate): Estimate
    {
        Gate::forUser($actor)->authorize('submit', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::DRAFT)) {
            throw ValidationException::withMessages(['estimate' => __('Only a draft estimate can be submitted.')]);
        }

        if (! $estimate->lines()->exists() && ! $estimate->materialLines()->exists()) {
            throw ValidationException::withMessages(['estimate' => __('Add at least one line before submitting.')]);
        }

        DB::transaction(function () use ($actor, $estimate): void {
            $this->moveTo($estimate, EstimateStatus::SUBMITTED, $actor);
            EstimateSubmitted::dispatch($estimate, $actor);
        });

        Notification::send($this->approvers->recipients($estimate)->reject(fn (User $user): bool => $user->is($actor)), new EstimateAwaitingApproval($estimate));

        return $estimate;
    }
}
