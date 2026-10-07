<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Services\ScheduleTriggers;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Replaces a project's payment schedule (docs/04 §3.5, spec P12). Percent and amount stay in
 * sync against the deed; while the contract is signed the Σ must equal the deed ± ৳1 (PRJ-BR-05).
 * Lines already invoiced or paid (06) are kept as they are.
 */
class SavePaymentSchedule
{
    public function __construct(private ScheduleTriggers $triggers) {}

    /**
     * @param  mixed  $input  list of lines
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, mixed $input): void
    {
        Gate::forUser($actor)->authorize('manageContract', $project);

        $contract = $project->contract()->with('status')->first();

        if ($contract?->isTerminated()) {
            throw ValidationException::withMessages(['schedule' => __('The contract was terminated.')]);
        }

        $deed = BigDecimal::of($contract !== null ? $contract->deed_amount : $project->contract_value)->toScale(2);
        $locked = $project->schedules()->whereHas('status', fn ($query) => $query->whereIn('code', [ScheduleStatus::INVOICED, ScheduleStatus::PAID]))->get();
        $lines = $this->validate($project, $deed, is_array($input) ? array_values($input) : [], $locked->pluck('id')->all());

        $total = $locked->reduce(fn (BigDecimal $sum, PaymentSchedule $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero());

        foreach ($lines as $line) {
            $total = $total->plus($line['amount']);
        }

        if ($contract?->isSigned() && $total->minus($deed)->abs()->isGreaterThan(SignContract::TOLERANCE)) {
            throw ValidationException::withMessages(['schedule' => __('The schedule must total the deed amount :deed while the contract is signed (now :total).', [
                'deed' => Money::format($deed), 'total' => Money::format($total),
            ])]);
        }

        DB::transaction(function () use ($project, $lines, $locked): void {
            $keep = [...$locked->pluck('id')->all(), ...array_filter(array_column($lines, 'id'))];
            $project->schedules()->whereNotIn('id', $keep)->get()->each->delete();
            $pending = ScheduleStatus::idFor(ScheduleStatus::PENDING);

            foreach ($lines as $index => $line) {
                $schedule = $line['id'] !== null ? $project->schedules()->findOrFail($line['id']) : new PaymentSchedule;
                $attributes = $line;
                unset($attributes['id']);

                $schedule->fill([...$attributes, 'sort_order' => $index + 1]);
                $schedule->forceFill(['project_id' => $project->id, 'schedule_status_id' => $schedule->schedule_status_id ?? $pending])->save();
            }

            $this->triggers->evaluate($project->fresh() ?? $project);
        });
    }

    /**
     * @param  list<mixed>  $input
     * @param  list<int>  $lockedIds
     * @return list<array{id: int|null, milestone_name: string, schedule_trigger_id: int, trigger_ref_id: int|null, due_date: string|null, percent: string|null, amount: string}>
     *
     * @throws ValidationException
     */
    private function validate(Project $project, BigDecimal $deed, array $input, array $lockedIds): array
    {
        $rows = array_map(function (mixed $line): array {
            $line = is_array($line) ? $line : [];
            $clean = fn (mixed $value): mixed => is_string($value) ? (trim(str_replace(',', '', $value)) === '' ? null : trim(str_replace(',', '', $value))) : $value;

            return [
                'id' => is_numeric($line['id'] ?? null) ? (int) $line['id'] : null,
                'milestone_name' => is_string($line['milestone_name'] ?? null) ? trim($line['milestone_name']) : null,
                'schedule_trigger_id' => $line['schedule_trigger_id'] ?? null,
                'trigger_ref_id' => $clean($line['trigger_ref_id'] ?? null),
                'due_date' => $clean($line['due_date'] ?? null),
                'percent' => $clean($line['percent'] ?? null),
                'amount' => $clean($line['amount'] ?? null),
            ];
        }, $input);

        $ownIds = $project->schedules()->pluck('id')->all();
        $triggers = ScheduleTrigger::query()->pluck('code', 'id')->all();

        $validator = Validator::make(['schedule' => $rows], [
            'schedule' => ['array', 'max:50'],
            'schedule.*.milestone_name' => ['required', 'string', 'max:200'],
            'schedule.*.schedule_trigger_id' => ['required', 'integer', 'exists:schedule_triggers,id'],
            'schedule.*.trigger_ref_id' => ['nullable', 'integer'],
            'schedule.*.due_date' => ['nullable', 'date'],
            'schedule.*.percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'schedule.*.amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999999'],
        ], [], ['schedule.*.milestone_name' => __('milestone'), 'schedule.*.schedule_trigger_id' => __('trigger')]);

        $validator->after(function (ValidatorInstance $validator) use ($rows, $ownIds, $lockedIds, $triggers, $project): void {
            foreach ($rows as $index => $row) {
                if ($row['id'] !== null && (! in_array($row['id'], $ownIds, true) || in_array($row['id'], $lockedIds, true))) {
                    $validator->errors()->add("schedule.{$index}.id", __('This milestone cannot be changed.'));
                }

                if ($row['percent'] === null && $row['amount'] === null) {
                    $validator->errors()->add("schedule.{$index}.amount", __('Enter a percent or an amount.'));
                }

                $code = $triggers[(int) $row['schedule_trigger_id']] ?? null;
                $ref = $row['trigger_ref_id'] !== null ? (int) $row['trigger_ref_id'] : null;

                $refOk = match ($code) {
                    ScheduleTrigger::PHASE => $ref !== null && ProjectPhase::query()->whereKey($ref)->exists(),
                    ScheduleTrigger::APPROVAL => $ref !== null && $project->approvals()->whereKey($ref)->exists(),
                    ScheduleTrigger::TASK => $ref !== null && $project->tasks()->whereKey($ref)->exists(),
                    default => true,
                };

                if (! $refOk) {
                    $validator->errors()->add("schedule.{$index}.trigger_ref_id", __('Choose what triggers this milestone.'));
                }

                if ($code === ScheduleTrigger::DATE && $row['due_date'] === null) {
                    $validator->errors()->add("schedule.{$index}.due_date", __('Enter the due date.'));
                }
            }
        });

        $validator->validate();

        return array_map(function (array $row) use ($deed, $triggers): array {
            $code = $triggers[(int) $row['schedule_trigger_id']] ?? null;

            if ($row['amount'] !== null) {
                $amount = BigDecimal::of($row['amount'])->toScale(2, RoundingMode::HalfUp);
                $percent = $deed->isZero() ? null : (string) $amount->multipliedBy(100)->dividedBy($deed, 4, RoundingMode::HalfUp);
            } else {
                $amount = $deed->multipliedBy($row['percent'])->dividedBy(100, 2, RoundingMode::HalfUp);
                $percent = (string) BigDecimal::of($row['percent'])->toScale(4, RoundingMode::HalfUp);
            }

            return [
                'id' => $row['id'],
                'milestone_name' => (string) $row['milestone_name'],
                'schedule_trigger_id' => (int) $row['schedule_trigger_id'],
                'trigger_ref_id' => in_array($code, [ScheduleTrigger::PHASE, ScheduleTrigger::APPROVAL, ScheduleTrigger::TASK], true) ? (int) $row['trigger_ref_id'] : null,
                'due_date' => $row['due_date'],
                'percent' => $percent,
                'amount' => (string) $amount,
            ];
        }, $rows);
    }
}
