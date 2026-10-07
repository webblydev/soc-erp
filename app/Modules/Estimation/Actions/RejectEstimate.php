<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ChangesEstimateStatus;
use App\Modules\Estimation\Events\EstimateRejected;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Notifications\EstimateDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SUBMITTED → REJECTED with a note (docs/05 §6.1, spec E9). The preparer edits it back to DRAFT.
 */
class RejectEstimate
{
    use ChangesEstimateStatus;

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Estimate $estimate, ?string $note): Estimate
    {
        Gate::forUser($actor)->authorize('approve', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::SUBMITTED)) {
            throw ValidationException::withMessages(['estimate' => __('Only a submitted estimate can be rejected.')]);
        }

        $note = Validator::make(['note' => is_string($note) ? trim($note) : $note], ['note' => ['required', 'string', 'max:2000']])->validate()['note'];

        DB::transaction(function () use ($actor, $estimate, $note): void {
            $this->moveTo($estimate, EstimateStatus::REJECTED, $actor, $note, ['rejection_note' => $note]);
            EstimateRejected::dispatch($estimate, $actor);
        });

        $preparer = $estimate->preparer->user;

        if ($preparer !== null && $preparer->is_active && ! $preparer->is($actor)) {
            $preparer->notify(new EstimateDecided($estimate, approved: false));
        }

        return $estimate;
    }
}
