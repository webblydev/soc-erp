<?php

namespace App\Modules\Estimation\Livewire\Estimates;

use App\Models\User;
use App\Modules\Estimation\Actions\ApproveEstimate;
use App\Modules\Estimation\Actions\BuildBudgetFromEstimate;
use App\Modules\Estimation\Actions\DeleteEstimate;
use App\Modules\Estimation\Actions\RejectEstimate;
use App\Modules\Estimation\Actions\ReviseEstimate;
use App\Modules\Estimation\Actions\SubmitEstimate;
use App\Modules\Estimation\Models\Estimate;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Estimate detail (docs/05 §5.2 actions, spec §5.3): header, revision strip, key figures, lines,
 * materials, history, documents and notes, with the workflow actions the user may take.
 */
class Show extends Component
{
    public Estimate $estimate;

    #[Url(except: 'lines')]
    public string $tab = 'lines';

    public string $rejectNote = '';

    public string $revisePurpose = '';

    public function mount(Estimate $estimate): void
    {
        $this->authorize('view', $estimate);
        $this->estimate = $estimate;
    }

    public function submit(SubmitEstimate $submitEstimate): void
    {
        $this->run(fn () => $submitEstimate->handle($this->actor(), $this->estimate), __('Submitted for approval.'));
    }

    public function approve(ApproveEstimate $approveEstimate): void
    {
        $this->run(fn () => $approveEstimate->handle($this->actor(), $this->estimate), __('Estimate approved.'));
    }

    public function reject(RejectEstimate $rejectEstimate): void
    {
        $this->resetErrorBag();

        try {
            $rejectEstimate->handle($this->actor(), $this->estimate, $this->rejectNote);
        } catch (ValidationException $exception) {
            $this->addError('rejectNote', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->reset('rejectNote');
        $this->dispatch('close-sheet-estimate-reject');
        $this->refreshEstimate(__('Estimate rejected.'));
    }

    public function revise(ReviseEstimate $reviseEstimate): void
    {
        $this->resetErrorBag();

        try {
            $revision = $reviseEstimate->handle($this->actor(), $this->estimate, $this->revisePurpose);
        } catch (ValidationException $exception) {
            $this->addError('revisePurpose', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        session()->flash('success', __('Revision :number started.', ['number' => $revision->estimate_number]));
        $this->redirectRoute('estimation.estimates.edit', $revision, navigate: true);
    }

    public function buildBudget(BuildBudgetFromEstimate $buildBudget): void
    {
        $this->run(fn () => $buildBudget->handle($this->estimate, $this->actor()), __('Budget rebuilt from this estimate.'));
    }

    public function delete(DeleteEstimate $deleteEstimate): void
    {
        try {
            $deleteEstimate->handle($this->actor(), $this->estimate);
        } catch (ValidationException $exception) {
            $this->dispatch('close-sheet-estimate-delete');
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        session()->flash('success', __('Estimate deleted.'));
        $this->redirectRoute('estimation.estimates.index', navigate: true);
    }

    private function run(\Closure $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->refreshEstimate($message);
    }

    private function refreshEstimate(string $message): void
    {
        $this->estimate->refresh();
        $this->dispatch('toast', type: 'success', description: $message);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $estimate = $this->estimate->load([
            'kind', 'status', 'project:id,project_number,name,project_manager_id,customer_id', 'preparer:id,full_name', 'checker:id,full_name', 'approver:id,name',
            'sections', 'lines.unit:id,symbol', 'lines.workItem:id,code', 'lines.costCategory:id,name', 'materialLines.material:id,name', 'materialLines.unit:id,symbol',
        ]);
        $family = Estimate::query()->familyOf($estimate)->with('status:id,name,color')->orderBy('revision_no')->get(['id', 'estimate_number', 'revision_no', 'estimate_status_id', 'total_amount', 'root_estimate_id']);
        $tabs = array_filter([
            'lines' => $estimate->hasWorkLines() ? __('Lines') : null,
            'materials' => $estimate->hasMaterialLines() ? __('Materials') : null,
            'history' => __('Revisions & history'),
            'documents' => __('Documents'),
            'notes' => __('Notes'),
        ]);

        if (! array_key_exists($this->tab, $tabs)) {
            $this->tab = (string) array_key_first($tabs);
        }

        return view('livewire.estimation.estimates.show', [
            'family' => $family,
            'previous' => $family->firstWhere('revision_no', $estimate->revision_no - 1),
            'tabs' => $tabs,
            'groups' => $estimate->lines->groupBy(fn ($line): string => (string) $estimate->sections->firstWhere('id', $line->estimate_section_id)?->name),
            'statusHistory' => $this->tab === 'history' ? $estimate->statusHistories()->with(['status:id,name', 'changer:id,name'])->get() : collect(),
        ])
            ->title($estimate->estimate_number)
            ->layoutData(['back' => route('estimation.estimates.index')]);
    }
}
