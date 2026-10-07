<?php

namespace App\Modules\Estimation\Livewire\Budget;

use App\Models\User;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Estimation\Actions\SaveBudget;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Services\BudgetCostSources;
use App\Modules\Estimation\Services\BudgetSummary;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Project budget (docs/05 §5.4, spec §5.5): budget vs committed vs actual per cost category, the
 * lines behind each, the hand-made lines editor and the revision log.
 */
class Show extends Component
{
    public Project $project;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public string $reason = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewBudget', $project);
        $this->project = $project;
        $this->loadLines();
    }

    public function addLine(): void
    {
        $this->lines[] = ['id' => null, 'cost_category_id' => null, 'description' => '', 'budget_qty' => '', 'unit_id' => null, 'budget_amount' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(SaveBudget $saveBudget): void
    {
        $this->resetErrorBag();

        try {
            $saveBudget->handle($this->actor(), $this->project, $this->lines, $this->reason);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        $this->reset('reason');
        $this->loadLines();
        $this->dispatch('close-sheet-budget-edit');
        $this->dispatch('toast', type: 'success', description: __('Budget saved.'));
    }

    private function loadLines(): void
    {
        $this->lines = $this->project->budgetLines()->whereNull('source_estimate_id')->get()->map(fn ($line): array => [
            'id' => $line->id,
            'cost_category_id' => $line->cost_category_id,
            'description' => (string) $line->description,
            'budget_qty' => $line->budget_qty !== null ? rtrim(rtrim((string) $line->budget_qty, '0'), '.') : '',
            'unit_id' => $line->unit_id,
            'budget_amount' => rtrim(rtrim((string) $line->budget_amount, '0'), '.'),
        ])->values()->all();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(BudgetSummary $summary, BudgetCostSources $sources): View
    {
        return view('livewire.estimation.budget.show', [
            'summary' => $summary->for($this->project),
            'hasCostSources' => ! $sources->isEmpty(),
            'canManage' => $this->actor()->can('manageBudget', $this->project),
            'revisions' => $this->project->budgetRevisions()->with('approver:id,name')->get(),
            'needsReason' => $this->project->budgetRevisions()->exists(),
            'categories' => CostCategory::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->lines)->pluck('cost_category_id')->filter()))->ordered()->get(['id', 'name']),
            'units' => Unit::query()->active()->ordered()->get(['id', 'symbol']),
        ])
            ->title(__('Budget · :number', ['number' => $this->project->project_number]))
            ->layoutData(['back' => route('projects.projects.show', ['project' => $this->project, 'tab' => 'estimates'])]);
    }
}
