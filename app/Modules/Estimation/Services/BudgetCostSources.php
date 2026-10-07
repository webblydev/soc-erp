<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Estimation\Contracts\BudgetCostSource;
use App\Modules\Projects\Models\Project;
use Brick\Math\BigDecimal;

/**
 * Registry of committed and actual cost providers (spec E2). Empty until Purchases (07) and
 * Accounting (08) register theirs, so both columns read 0.
 */
final class BudgetCostSources
{
    /** @var list<class-string<BudgetCostSource>> */
    private array $sources = [];

    /**
     * @param  class-string<BudgetCostSource>  $source
     */
    public function register(string $source): void
    {
        $this->sources[] = $source;
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }

    /**
     * @return array<int, string>
     */
    public function committed(Project $project): array
    {
        return $this->sum(fn (BudgetCostSource $source): array => $source->committed($project));
    }

    /**
     * @return array<int, string>
     */
    public function actual(Project $project): array
    {
        return $this->sum(fn (BudgetCostSource $source): array => $source->actual($project));
    }

    /**
     * @param  callable(BudgetCostSource): array<int, string>  $read
     * @return array<int, string>
     */
    private function sum(callable $read): array
    {
        $totals = [];

        foreach ($this->sources as $class) {
            foreach ($read(app($class)) as $categoryId => $amount) {
                $totals[$categoryId] = (string) BigDecimal::of($totals[$categoryId] ?? '0')->plus($amount)->toScale(2);
            }
        }

        return $totals;
    }
}
