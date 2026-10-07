<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Estimation\Models\Estimate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Estimate totals (spec E7). Kinds with work lines total their work lines (material lines on a BOQ
 * are a statement); a material estimate totals its material lines. Overhead, profit and VAT chain
 * on top of each other.
 */
final class EstimateTotals
{
    /**
     * @return array{subtotal: string, overhead_amount: string, profit_amount: string, vat_amount: string, total_amount: string}
     */
    public function calculate(string $subtotal, ?string $overheadPct, ?string $profitPct, ?string $vatPct): array
    {
        $base = BigDecimal::of($subtotal)->toScale(2, RoundingMode::HalfUp);
        $overhead = self::percentOf($base, $overheadPct);
        $profit = self::percentOf($base->plus($overhead), $profitPct);
        $vat = self::percentOf($base->plus($overhead)->plus($profit), $vatPct);

        return [
            'subtotal' => (string) $base,
            'overhead_amount' => (string) $overhead,
            'profit_amount' => (string) $profit,
            'vat_amount' => (string) $vat,
            'total_amount' => (string) $base->plus($overhead)->plus($profit)->plus($vat),
        ];
    }

    /**
     * Recalculate and store the estimate's totals from its saved lines.
     */
    public function refresh(Estimate $estimate): void
    {
        $amounts = $estimate->totalsFromMaterials()
            ? $estimate->materialLines()->pluck('amount')
            : $estimate->lines()->pluck('amount');

        $subtotal = $amounts->reduce(fn (BigDecimal $sum, mixed $amount): BigDecimal => $sum->plus((string) $amount), BigDecimal::zero());

        $estimate->forceFill($this->calculate((string) $subtotal, $estimate->overhead_pct, $estimate->profit_pct, $estimate->vat_pct))->save();
    }

    private static function percentOf(BigDecimal $amount, ?string $percent): BigDecimal
    {
        if ($percent === null || $percent === '') {
            return BigDecimal::zero()->toScale(2);
        }

        return $amount->multipliedBy($percent)->dividedBy(100, 2, RoundingMode::HalfUp);
    }
}
