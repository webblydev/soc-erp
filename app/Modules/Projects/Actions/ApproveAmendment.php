<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\ProjectContractAmendment;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Services\ServiceLines;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies a draft amendment (spec P11, PRJ-AC-03): the proposed lines replace the services, the
 * contract value and deed follow, and an increase gets its own MANUAL milestone.
 */
class ApproveAmendment
{
    public function __construct(private ServiceLines $serviceLines) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectContractAmendment $amendment): void
    {
        $contract = $amendment->contract()->with(['status', 'project'])->firstOrFail();
        $project = $contract->project;

        Gate::forUser($actor)->authorize('manageContract', $project);

        if (! $amendment->isDraft() || ! $contract->isSigned()) {
            throw ValidationException::withMessages(['amendment' => __('Only a draft amendment of a signed contract can be approved.')]);
        }

        DB::transaction(function () use ($actor, $amendment, $contract, $project): void {
            $before = BigDecimal::of($project->contract_value);
            $existing = $project->services()->pluck('id')->all();

            $lines = [];

            foreach ($amendment->lines()->get() as $line) {
                $lines[] = [
                    'id' => in_array($line->project_service_id, $existing, true) ? $line->project_service_id : null,
                    'service_id' => $line->service_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'rate' => $line->rate,
                    'discount_amount' => $line->discount_amount,
                    'amount' => $line->amount,
                    'project_service_status_id' => $line->project_service_status_id,
                ];
            }

            $this->serviceLines->apply($project, $lines);
            $project->refresh();

            $after = BigDecimal::of($project->contract_value);
            $change = $after->minus($before);

            $amendment->forceFill([
                'status' => ProjectContractAmendment::APPROVED,
                'value_change' => (string) $change,
                'new_deed_amount' => (string) $after,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $contract->forceFill(['deed_amount' => (string) $after, 'contract_status_id' => ContractStatus::idFor(ContractStatus::AMENDED)])->save();

            if ($change->isPositive()) {
                (new PaymentSchedule([
                    'sort_order' => (int) $project->schedules()->max('sort_order') + 1,
                    'milestone_name' => __('Amendment :no', ['no' => $amendment->amendment_no]),
                    'schedule_trigger_id' => ScheduleTrigger::idFor(ScheduleTrigger::MANUAL),
                    'percent' => (string) $change->multipliedBy(100)->dividedBy($after, 4, RoundingMode::HalfUp),
                    'amount' => (string) $change,
                ]))->forceFill(['project_id' => $project->id, 'schedule_status_id' => ScheduleStatus::idFor(ScheduleStatus::PENDING)])->save();
            }
        });
    }
}
