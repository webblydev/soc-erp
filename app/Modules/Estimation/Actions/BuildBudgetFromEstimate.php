<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Services\BudgetRevisions;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the budget lines that came from any revision of an estimate's root with lines from the
 * approved estimate (docs/05 §6.1, spec E11). A BOQ gives one line per cost category of its work
 * lines (no category → OTHER, deductions net out, a net ≤ 0 is skipped); a material estimate gives
 * one MATERIAL line per material line.
 */
class BuildBudgetFromEstimate
{
    public function __construct(private BudgetRevisions $revisions) {}

    /**
     * @param  bool  $authorize  false when run by the approval listener
     *
     * @throws ValidationException
     */
    public function handle(Estimate $estimate, ?User $actor, bool $authorize = true): void
    {
        $project = $estimate->project;

        if ($authorize) {
            Gate::forUser($actor)->authorize('manageBudget', $project);
        }

        if (! $estimate->hasStatus(EstimateStatus::APPROVED) || ! in_array($estimate->kind->code, [EstimateKind::BOQ, EstimateKind::MATERIAL], true)) {
            throw ValidationException::withMessages(['estimate' => __('Only an approved BOQ or material estimate builds the budget.')]);
        }

        DB::transaction(function () use ($estimate, $project, $actor): void {
            $oldTotal = $this->revisions->total($project);
            $family = Estimate::withTrashed()->familyOf($estimate)->pluck('id');

            $project->budgetLines()->whereIn('source_estimate_id', $family)->delete();

            $order = (int) $project->budgetLines()->max('sort_order');

            foreach ($this->linesFor($estimate) as $line) {
                $project->budgetLines()->create([...$line, 'source_estimate_id' => $estimate->id, 'sort_order' => ++$order]);
            }

            $this->revisions->record($project, $oldTotal, __('Estimate :number approved', ['number' => $estimate->estimate_number]), $actor);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linesFor(Estimate $estimate): array
    {
        if ($estimate->kind->code === EstimateKind::MATERIAL) {
            $material = CostCategory::idFor(CostCategory::MATERIAL);

            return array_values($estimate->materialLines()->get()->map(fn ($line): array => [
                'cost_category_id' => $material,
                'material_id' => $line->material_id,
                'material_name' => $line->material_name,
                'budget_qty' => $line->total_qty,
                'unit_id' => $line->unit_id,
                'budget_amount' => $line->amount,
            ])->all());
        }

        $other = CostCategory::idFor(CostCategory::OTHER);

        return array_values($estimate->lines()->get()
            ->groupBy(fn (EstimateLine $line): int => $line->cost_category_id ?? $other)
            ->map(fn ($lines, int $categoryId): array => [
                'cost_category_id' => $categoryId,
                'budget_amount' => (string) $lines->reduce(fn (BigDecimal $sum, EstimateLine $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero()),
            ])
            ->filter(fn (array $line): bool => BigDecimal::of($line['budget_amount'])->isPositive())
            ->all());
    }
}
