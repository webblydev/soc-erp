<?php

namespace App\Modules\Estimation\Livewire\Budget;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Services\BudgetCostSources;
use App\Modules\Estimation\Services\BudgetSummary;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The project page's Estimates & Budget tab (spec E21): latest revisions of the project's
 * estimates and the budget summary.
 */
class ProjectTab extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('viewEstimates', $project);
        $this->project = $project;
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(BudgetSummary $summary, BudgetCostSources $sources): View
    {
        $canSeeBudget = $this->actor()->can('viewBudget', $this->project);

        return view('livewire.estimation.budget.project-tab', [
            'estimates' => Estimate::query()->where('project_id', $this->project->id)->latestRevisions()
                ->with(['kind:id,name', 'status:id,name,color'])->latest('estimate_date')->latest('id')->get(),
            'canCreate' => $this->actor()->can('createEstimate', $this->project) && $this->project->isOpen(),
            'canSeeBudget' => $canSeeBudget,
            'summary' => $canSeeBudget ? $summary->for($this->project) : null,
            'hasCostSources' => ! $sources->isEmpty(),
        ]);
    }
}
