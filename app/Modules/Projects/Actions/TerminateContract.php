<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Terminates the contract with a reason (spec P10). Services stay as they are.
 */
class TerminateContract
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): void
    {
        Gate::forUser($actor)->authorize('manageContract', $project);

        $contract = $project->contract()->with('status')->first();

        if ($contract === null || $contract->isTerminated()) {
            throw ValidationException::withMessages(['contract' => __('There is no contract to terminate.')]);
        }

        $data = Validator::make($input, ['reason' => ['required', 'string', 'max:2000']], [], ['reason' => __('reason')])->validate();

        DB::transaction(fn () => $contract->forceFill([
            'contract_status_id' => ContractStatus::idFor(ContractStatus::TERMINATED),
            'terminated_at' => now(),
            'termination_reason' => $data['reason'],
        ])->save());
    }
}
