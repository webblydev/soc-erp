<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

final class Money
{
    public const BASE_CURRENCY = 'BDT';

    /** @var array<string, string> */
    private const SYMBOLS = ['BDT' => '৳', 'USD' => '$'];

    /**
     * Build a money value rounded half-up to the currency scale (CM-BR-07).
     */
    public static function of(BigNumber|int|string $amount, string $currency = self::BASE_CURRENCY): BrickMoney
    {
        return BrickMoney::of($amount, $currency, roundingMode: RoundingMode::HalfUp);
    }

    /**
     * Format as "৳ 12,34,567.00" using Bangladeshi/Indian digit grouping.
     */
    public static function format(BrickMoney|BigNumber|int|string $amount, bool $withSymbol = true): string
    {
        $money = $amount instanceof BrickMoney ? $amount : self::of($amount);
        $decimal = $money->getAmount()->toScale(2, RoundingMode::HalfUp);

        [$integer, $fraction] = explode('.', (string) $decimal->abs());

        $code = $money->getCurrency()->getCurrencyCode();
        $symbol = $withSymbol ? (self::SYMBOLS[$code] ?? $code).' ' : '';

        return ($decimal->isNegative() ? '-' : '').$symbol.self::groupDigits($integer).'.'.$fraction;
    }

    /**
     * Format a DECIMAL(18,4) rate with BD grouping: at least 2 and at most 4 decimals, no symbol.
     */
    public static function formatRate(?string $rate): string
    {
        if ($rate === null || $rate === '') {
            return '—';
        }

        $decimal = BigDecimal::of($rate)->toScale(4, RoundingMode::HalfUp);
        [$integer, $fraction] = explode('.', (string) $decimal->abs());
        $fraction = str_pad(rtrim($fraction, '0'), 2, '0');

        return ($decimal->isNegative() ? '-' : '').self::groupDigits($integer).'.'.$fraction;
    }

    private static function groupDigits(string $integer): string
    {
        if (strlen($integer) <= 3) {
            return $integer;
        }

        $leading = strrev(implode(',', str_split(strrev(substr($integer, 0, -3)), 2)));

        return $leading.','.substr($integer, -3);
    }
}
