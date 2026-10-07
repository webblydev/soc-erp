<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Signs a draft contract (spec P10): the deed equals the contract value and the payment schedule
 * totals the deed within ৳1 (PRJ-BR-05, PRJ-AC-02). An enquiry becomes CONTRACTED.
 */
class SignContract
{
    public const TOLERANCE = '1.00';

    public function __construct(private ChangeProjectStatus $changeStatus) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project): void
    {
        Gate::forUser($actor)->authorize('manageContract', $project);

        $contract = $project->contract()->with('status')->first();

        if ($contract === null || ! $contract->isDraft()) {
            throw ValidationException::withMessages(['contract' => __('Only a draft contract can be signed.')]);
        }

        $deed = BigDecimal::of($project->contract_value);

        if ($deed->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages(['contract' => __('Add the services before signing; the contract value is zero.')]);
        }

        $scheduled = BigDecimal::of((string) $project->schedules()->sum('amount'))->toScale(2);
        $difference = $deed->minus($scheduled);

        if ($difference->abs()->isGreaterThan(self::TOLERANCE)) {
            throw ValidationException::withMessages(['schedule' => __('The payment schedule totals :scheduled against a deed of :deed (difference :difference).', [
                'scheduled' => Money::format($scheduled),
                'deed' => Money::format($deed),
                'difference' => Money::format($difference),
            ])]);
        }

        DB::transaction(function () use ($actor, $project, $contract): void {
            $contract->forceFill([
                'deed_amount' => $project->contract_value,
                'contract_status_id' => ContractStatus::idFor(ContractStatus::SIGNED),
                'signed_at' => now(),
                'signed_by_company_user_id' => $contract->signed_by_company_user_id ?? $actor->id,
            ])->save();

            if ($project->status->code === ProjectStatus::ENQUIRY) {
                $this->changeStatus->apply($actor, $project, ProjectStatus::idFor(ProjectStatus::CONTRACTED), $project->project_phase_id, __('Contract signed'));
            }
        });
    }
}
