<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits the project's contract (docs/04 §3.4, spec P10). A draft follows the
 * contract value; once signed only the terms can change.
 */
class SaveContract
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): ProjectContract
    {
        Gate::forUser($actor)->authorize('manageContract', $project);

        $contract = $project->contract()->with('status')->first();

        if ($contract === null && ($project->isInternal() || ! $project->isOpen())) {
            throw ValidationException::withMessages(['contract' => __('Internal and closed projects have no contract.')]);
        }

        if ($contract !== null && $contract->isTerminated()) {
            throw ValidationException::withMessages(['contract' => __('The contract was terminated.')]);
        }

        if ($contract !== null && ! $contract->isDraft()) {
            $data = Validator::make($input, ['terms' => ['nullable', 'string', 'max:20000']])->validate();

            DB::transaction(fn () => $contract->forceFill(['terms' => $data['terms'] ?? null])->save());

            return $contract;
        }

        $data = Validator::make($input, [
            'contract_number' => ['nullable', 'string', 'max:60'],
            'agreement_date' => ['required', 'date'],
            'vat_inclusive' => ['boolean'],
            'advance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retention_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'defect_liability_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'signed_by_customer' => ['nullable', 'string', 'max:150'],
            'signed_by_company_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'terms' => ['nullable', 'string', 'max:20000'],
        ], [], ['signed_by_company_user_id' => __('signed by (company)')])->validate();

        return DB::transaction(function () use ($project, $contract, $data): ProjectContract {
            $contract ??= new ProjectContract(['vat_inclusive' => false]);
            $contract->fill(Arr::only($data, $contract->getFillable()));
            $contract->forceFill([
                'project_id' => $project->id,
                'deed_amount' => $project->contract_value,
                'contract_status_id' => $contract->contract_status_id ?? ContractStatus::idFor(ContractStatus::DRAFT),
            ])->save();

            return $contract;
        });
    }
}
