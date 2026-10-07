<?php

namespace App\Modules\Estimation\Services;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Support\Facades\Settings;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Who approves an estimate (ES-BR-04, spec E9, E17): the project's PM up to
 * estimation.pm_approval_limit, management above it.
 */
final class EstimateApprovers
{
    public function limit(): string
    {
        return (string) Settings::get('estimation.pm_approval_limit', 5000000);
    }

    public function isWithinPmLimit(Estimate $estimate): bool
    {
        return BigDecimal::of($estimate->total_amount)->isLessThanOrEqualTo($this->limit());
    }

    /**
     * Active users to notify when the estimate is submitted.
     *
     * @return Collection<int, User>
     */
    public function recipients(Estimate $estimate): Collection
    {
        if ($this->isWithinPmLimit($estimate)) {
            $pm = $estimate->project->manager?->user;

            if ($pm !== null && $pm->is_active) {
                return collect([$pm]);
            }
        }

        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'management'))
            ->get();
    }
}
