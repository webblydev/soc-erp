<?php

namespace App\Modules\Estimation\Services;

use App\Models\User;
use App\Modules\Projects\Actions\SetBudgetCost;
use App\Modules\Projects\Models\Project;
use Brick\Math\BigDecimal;

/**
 * Logs every change of a project's budget total (docs/05 §3.6, ES-BR-05, spec E11) and keeps the
 * project's budget cost in step. Callers hold the transaction.
 */
final class BudgetRevisions
{
    public function __construct(private SetBudgetCost $setBudgetCost) {}

    public function total(Project $project): string
    {
        return (string) $project->budgetLines()->pluck('budget_amount')
            ->reduce(fn (BigDecimal $sum, mixed $amount): BigDecimal => $sum->plus((string) $amount), BigDecimal::zero())->toScale(2);
    }

    /**
     * Whether the budget already has a revision, so later changes need a reason.
     */
    public function isApproved(Project $project): bool
    {
        return $project->budgetRevisions()->exists();
    }

    /**
     * Write a revision when the total moved from $oldTotal.
     */
    public function record(Project $project, string $oldTotal, ?string $reason, ?User $actor): void
    {
        $newTotal = $this->total($project);

        if (BigDecimal::of($newTotal)->isEqualTo($oldTotal)) {
            return;
        }

        $project->budgetRevisions()->create([
            'revision_no' => (int) $project->budgetRevisions()->max('revision_no') + 1,
            'reason' => $reason,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);

        $this->setBudgetCost->handle($project, $newTotal);
    }
}
