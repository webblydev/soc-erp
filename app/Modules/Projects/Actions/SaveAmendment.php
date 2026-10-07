<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContractAmendment;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Services\ServiceLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a draft amendment with its proposed service lines (spec P11). Existing lines
 * left out of the proposal are kept as CANCELLED lines, so nothing billed later disappears.
 */
class SaveAmendment
{
    public function __construct(private ServiceLines $serviceLines) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input, ?ProjectContractAmendment $amendment = null): ProjectContractAmendment
    {
        Gate::forUser($actor)->authorize('manageContract', $project);

        $contract = $project->contract()->with('status')->first();

        if ($contract === null || ! $contract->isSigned()) {
            throw ValidationException::withMessages(['amendment' => __('Amendments need a signed contract.')]);
        }

        if ($amendment !== null && ($amendment->project_contract_id !== $contract->id || ! $amendment->isDraft())) {
            throw ValidationException::withMessages(['amendment' => __('Only a draft amendment of this contract can be edited.')]);
        }

        if ($amendment === null && $contract->amendments()->where('status', ProjectContractAmendment::DRAFT)->exists()) {
            throw ValidationException::withMessages(['amendment' => __('Approve or delete the open draft amendment first.')]);
        }

        $data = Validator::make($input, [
            'amendment_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [], ['amendment_date' => __('amendment date'), 'reason' => __('reason')])->validate();

        $lines = $this->serviceLines->validate($input['services'] ?? [], $project, array_values($project->services()->pluck('service_id')->map(fn (mixed $id): int => (int) $id)->all()), withStatus: true);
        $lines = $this->withDroppedLinesCancelled($project, $lines);

        return DB::transaction(function () use ($contract, $amendment, $data, $lines): ProjectContractAmendment {
            $amendment ??= new ProjectContractAmendment;
            $amendment->fill($data);

            if (! $amendment->exists) {
                $amendment->forceFill([
                    'project_contract_id' => $contract->id,
                    'amendment_no' => (int) $contract->amendments()->withTrashed()->max('amendment_no') + 1,
                    'status' => ProjectContractAmendment::DRAFT,
                ]);
            }

            $amendment->save();
            $amendment->lines()->delete();

            foreach ($lines as $index => $line) {
                $amendment->lines()->create([...$line, 'project_service_id' => $line['id'], 'sort_order' => $index + 1]);
            }

            return $amendment;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function withDroppedLinesCancelled(Project $project, array $lines): array
    {
        $kept = array_filter(array_column($lines, 'id'));
        $cancelled = ProjectServiceStatus::idFor(ProjectServiceStatus::CANCELLED);

        foreach ($project->services()->whereNotIn('id', $kept)->get() as $dropped) {
            $lines[] = [
                'id' => $dropped->id,
                'service_id' => $dropped->service_id,
                'description' => $dropped->description,
                'quantity' => $dropped->quantity,
                'unit_id' => $dropped->unit_id,
                'rate' => $dropped->rate,
                'discount_amount' => $dropped->discount_amount,
                'amount' => $dropped->amount,
                'project_service_status_id' => $cancelled,
            ];
        }

        return $lines;
    }
}
