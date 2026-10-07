<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\ProjectBudgetLine;
use App\Modules\Projects\Models\Project;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Budget vs committed vs actual per cost category (docs/05 §5.4, spec E12).
 *
 * @phpstan-type BudgetRow array{category: CostCategory, budget: string, committed: string, actual: string, remaining: string, variance_pct: string|null, lines: Collection<int, ProjectBudgetLine>}
 */
final class BudgetSummary
{
    public function __construct(private BudgetCostSources $sources) {}

    /**
     * @return array{rows: list<BudgetRow>, totals: array{budget: string, committed: string, actual: string, remaining: string, variance_pct: string|null}}
     */
    public function for(Project $project): array
    {
        $lines = $project->budgetLines()->with(['costCategory', 'material', 'workItem', 'unit', 'sourceEstimate'])->get()->groupBy('cost_category_id');
        $committed = $this->sources->committed($project);
        $actual = $this->sources->actual($project);
        $categoryIds = collect($lines->keys())->merge(array_keys($committed))->merge(array_keys($actual))->unique();

        $rows = array_values(CostCategory::query()->withTrashed()->whereIn('id', $categoryIds)->ordered()->get()
            ->map(function (CostCategory $category) use ($lines, $committed, $actual): array {
                $categoryLines = $lines->get($category->id, collect());
                $budget = $categoryLines->reduce(fn (BigDecimal $sum, ProjectBudgetLine $line): BigDecimal => $sum->plus($line->budget_amount), BigDecimal::zero());

                return ['category' => $category, ...self::figures($budget, $committed[$category->id] ?? '0', $actual[$category->id] ?? '0'), 'lines' => $categoryLines];
            })->all());

        $sum = fn (string $key): BigDecimal => array_reduce($rows, fn (BigDecimal $total, array $row): BigDecimal => $total->plus($row[$key]), BigDecimal::zero());

        return ['rows' => $rows, 'totals' => self::figures($sum('budget'), (string) $sum('committed'), (string) $sum('actual'))];
    }

    /**
     * @return array{budget: string, committed: string, actual: string, remaining: string, variance_pct: string|null}
     */
    private static function figures(BigDecimal $budget, string $committed, string $actual): array
    {
        $budget = $budget->toScale(2);
        $committed = BigDecimal::of($committed)->toScale(2);
        $actual = BigDecimal::of($actual)->toScale(2);

        return [
            'budget' => (string) $budget,
            'committed' => (string) $committed,
            'actual' => (string) $actual,
            'remaining' => (string) $budget->minus($committed)->minus($actual),
            'variance_pct' => $budget->isZero() ? null : (string) $actual->minus($budget)->multipliedBy(100)->dividedBy($budget, 2, RoundingMode::HalfUp),
        ];
    }
}
