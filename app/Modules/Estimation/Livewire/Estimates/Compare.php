<?php

namespace App\Modules\Estimation\Livewire\Estimates;

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Services\EstimateComparison;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Compare two revisions of an estimate (docs/05 §5.3, ES-AC-03).
 */
class Compare extends Component
{
    public Estimate $estimate;

    #[Url(as: 'with')]
    public string $with = '';

    public bool $showSame = false;

    public function mount(Estimate $estimate): void
    {
        $this->authorize('view', $estimate);
        $this->estimate = $estimate;
    }

    public function render(EstimateComparison $comparison): View
    {
        $family = Estimate::query()->familyOf($this->estimate)->orderBy('revision_no')->get(['id', 'estimate_number', 'revision_no', 'root_estimate_id', 'subtotal', 'total_amount', 'estimate_kind_id']);
        $other = $family->firstWhere('estimate_number', $this->with)
            ?? $family->where('id', '!=', $this->estimate->id)->last();

        return view('livewire.estimation.estimates.compare', [
            'family' => $family,
            'other' => $other,
            'result' => $other !== null ? $comparison->between($this->estimate, Estimate::query()->findOrFail($other->id)) : null,
        ])
            ->title(__('Compare :number', ['number' => $this->estimate->estimate_number]))
            ->layoutData(['back' => route('estimation.estimates.show', $this->estimate)]);
    }
}
