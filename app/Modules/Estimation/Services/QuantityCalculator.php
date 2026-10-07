<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Catalog\Enums\MeasurementFormula;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Measurement sheet maths (docs/05 §3.4, ES-BR-03, spec E6): the quantity a formula gives, and a
 * line's amount. Quantities keep 4 decimals, amounts 2, both rounded half-up.
 */
final class QuantityCalculator
{
    /**
     * Nos × the dimensions the formula names. An empty nos counts as 1 and an empty dimension as 0;
     * a manual formula returns the typed quantity.
     */
    public static function quantity(MeasurementFormula $formula, ?string $nos, ?string $length, ?string $width, ?string $height, ?string $manual = null): string
    {
        if ($formula === MeasurementFormula::Manual) {
            return self::scale($manual ?? '0', 4);
        }

        $values = ['nos' => $nos, 'length' => $length, 'width' => $width, 'height' => $height];
        $result = BigDecimal::one();

        foreach ($formula->dimensions() as $dimension) {
            $value = $values[$dimension];
            $result = $result->multipliedBy($value === null || $value === '' ? ($dimension === 'nos' ? '1' : '0') : $value);
        }

        return (string) $result->toScale(4, RoundingMode::HalfUp);
    }

    /**
     * round(quantity × rate, 2); negative for a deduction line. An empty rate gives 0.
     */
    public static function amount(string $quantity, ?string $rate, bool $deduction = false): string
    {
        if ($rate === null || $rate === '') {
            return '0.00';
        }

        $amount = BigDecimal::of($quantity)->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);

        return (string) ($deduction ? $amount->negated() : $amount);
    }

    /**
     * Material total quantity: estimated × (1 + wastage % / 100), 4 decimals (spec E7).
     */
    public static function withWastage(string $estimated, ?string $wastagePct): string
    {
        $factor = BigDecimal::one()->plus(BigDecimal::of($wastagePct === null || $wastagePct === '' ? '0' : $wastagePct)->dividedBy(100, 6, RoundingMode::HalfUp));

        return (string) BigDecimal::of($estimated)->multipliedBy($factor)->toScale(4, RoundingMode::HalfUp);
    }

    /**
     * @param  int<0, max>  $scale
     */
    private static function scale(string $value, int $scale): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
    }
}
