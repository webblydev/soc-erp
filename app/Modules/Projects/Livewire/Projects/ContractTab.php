<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Projects\Actions\ApproveAmendment;
use App\Modules\Projects\Actions\DeleteAmendment;
use App\Modules\Projects\Actions\MarkMilestoneDue;
use App\Modules\Projects\Actions\SaveContract;
use App\Modules\Projects\Actions\SavePaymentSchedule;
use App\Modules\Projects\Actions\SignContract;
use App\Modules\Projects\Actions\TerminateContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ScheduleTrigger;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Contract & schedule tab of the project page (docs/04 §5.3 tab 2, spec P10–P12).
 */
class ContractTab extends Component
{
    public Project $project;

    /** @var array<string, mixed> */
    public array $contractForm = [];

    /** @var list<array<string, mixed>> */
    public array $scheduleLines = [];

    public string $terminationReason = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewContract', $project);
        $this->project = $project;
        $this->fillContractForm();
    }

    public function saveContract(SaveContract $saveContract): void
    {
        $this->run('contractForm', fn () => $saveContract->handle($this->actor(), $this->project, $this->contractForm));

        $this->dispatch('close-sheet-contract-form');
        $this->done(__('Contract saved.'));
    }

    public function sign(SignContract $signContract): void
    {
        $this->run(null, fn () => $signContract->handle($this->actor(), $this->project));

        $this->done(__('Contract signed.'));
        $this->dispatch('project-updated');
    }

    public function terminate(TerminateContract $terminateContract): void
    {
        $this->run(null, fn () => $terminateContract->handle($this->actor(), $this->project, ['reason' => $this->terminationReason]), ['reason' => 'terminationReason']);

        $this->terminationReason = '';
        $this->dispatch('close-sheet-contract-terminate');
        $this->done(__('Contract terminated.'));
    }

    public function editSchedule(): void
    {
        $this->authorize('manageContract', $this->project);

        $this->scheduleLines = array_values($this->project->schedules()->with('status')->get()->map(fn ($line): array => [
            'id' => $line->id,
            'milestone_name' => $line->milestone_name,
            'schedule_trigger_id' => $line->schedule_trigger_id,
            'trigger_ref_id' => $line->trigger_ref_id,
            'due_date' => (string) $line->due_date?->toDateString(),
            'percent' => $line->percent !== null ? rtrim(rtrim($line->percent, '0'), '.') : '',
            'amount' => (string) $line->amount,
            'status' => $line->status->name,
        ])->all());

        if ($this->scheduleLines === []) {
            $this->addScheduleLine();
        }

        $this->resetErrorBag();
        $this->dispatch('open-sheet-schedule-form');
    }

    public function addScheduleLine(): void
    {
        $this->scheduleLines = [...$this->scheduleLines, ['id' => null, 'milestone_name' => '', 'schedule_trigger_id' => ScheduleTrigger::idFor(ScheduleTrigger::MANUAL), 'trigger_ref_id' => null, 'due_date' => '', 'percent' => '', 'amount' => '']];
    }

    public function removeScheduleLine(int $index): void
    {
        $this->scheduleLines = array_values(array_filter($this->scheduleLines, fn (int $key): bool => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function saveSchedule(SavePaymentSchedule $savePaymentSchedule): void
    {
        $this->run(null, fn () => $savePaymentSchedule->handle($this->actor(), $this->project, $this->scheduleLines), [], 'schedule', 'scheduleLines');

        $this->dispatch('close-sheet-schedule-form');
        $this->done(__('Payment schedule saved.'));
    }

    public function markDue(int $lineId, MarkMilestoneDue $markMilestoneDue): void
    {
        $line = $this->project->schedules()->findOrFail($lineId);

        $this->run(null, fn () => $markMilestoneDue->handle($this->actor(), $line));
        $this->done(__('Milestone marked due.'));
    }

    public function approveAmendment(int $amendmentId, ApproveAmendment $approveAmendment): void
    {
        $amendment = $this->project->contract?->amendments()->findOrFail($amendmentId);
        abort_if($amendment === null, 404);

        $this->run(null, fn () => $approveAmendment->handle($this->actor(), $amendment));
        $this->done(__('Amendment approved.'));
        $this->dispatch('project-updated');
    }

    public function deleteAmendment(int $amendmentId, DeleteAmendment $deleteAmendment): void
    {
        $amendment = $this->project->contract?->amendments()->findOrFail($amendmentId);
        abort_if($amendment === null, 404);

        $this->run(null, fn () => $deleteAmendment->handle($this->actor(), $amendment));
        $this->done(__('Amendment deleted.'));
    }

    /**
     * Run an Action and show its errors under the given form, renaming keys where needed.
     *
     * @param  array<string, string>  $map
     *
     * @throws ValidationException
     */
    private function run(?string $prefix, Closure $callback, array $map = [], ?string $from = null, ?string $to = null): void
    {
        $this->resetErrorBag();

        try {
            $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(function (array $messages, string $key) use ($prefix, $map, $from, $to): array {
                if (isset($map[$key])) {
                    return [$map[$key] => $messages];
                }

                if ($from !== null && str_starts_with($key, $from.'.')) {
                    return [$to.substr($key, strlen($from)) => $messages];
                }

                return [$prefix !== null && ! in_array($key, ['contract', 'schedule'], true) ? "{$prefix}.{$key}" : $key => $messages];
            })->all());
        }
    }

    private function done(string $message): void
    {
        $this->project->refresh();
        $this->fillContractForm();
        $this->dispatch('toast', type: 'success', description: $message);
    }

    private function fillContractForm(): void
    {
        $contract = $this->project->contract;

        $this->contractForm = [
            'contract_number' => (string) $contract?->contract_number,
            'agreement_date' => (string) ($contract?->agreement_date?->toDateString() ?? today()->toDateString()),
            'vat_inclusive' => (bool) $contract?->vat_inclusive,
            'advance_pct' => $contract?->advance_pct !== null ? rtrim(rtrim($contract->advance_pct, '0'), '.') : '',
            'retention_pct' => $contract?->retention_pct !== null ? rtrim(rtrim($contract->retention_pct, '0'), '.') : '',
            'defect_liability_months' => (string) $contract?->defect_liability_months,
            'signed_by_customer' => (string) $contract?->signed_by_customer,
            'signed_by_company_user_id' => $contract?->signed_by_company_user_id,
            'terms' => (string) $contract?->terms,
        ];

        $this->contractForm = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $this->contractForm);
        $this->contractForm['agreement_date'] ??= today()->toDateString();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $contract = $this->project->contract()->with(['status', 'signedBy:id,name', 'amendments' => fn ($query) => $query->withCount('lines')->with('approver:id,name')])->first();
        $schedules = $this->project->schedules()->with(['status', 'trigger'])->get();
        $deed = BigDecimal::of($contract !== null ? $contract->deed_amount : $this->project->contract_value);
        $scheduled = $schedules->reduce(fn (BigDecimal $sum, $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero());

        return view('livewire.projects.projects.contract-tab', [
            'contract' => $contract,
            'schedules' => $schedules,
            'deed' => (string) $deed->toScale(2),
            'scheduled' => (string) $scheduled->toScale(2),
            'difference' => (string) $deed->minus($scheduled)->toScale(2),
            'canManage' => $this->actor()->can('manageContract', $this->project),
            'triggers' => ScheduleTrigger::query()->active()->ordered()->get(['id', 'name', 'code']),
            'phases' => ProjectPhase::query()->active()->ordered()->get(['id', 'name']),
            'projectApprovals' => $this->project->approvals()->with('type:id,name')->get(['id', 'approval_type_id', 'reference_no']),
            'projectTasks' => $this->project->tasks()->orderBy('sort_order')->get(['id', 'task_number', 'title']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
