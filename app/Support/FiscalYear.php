<?php

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class FiscalYear
{
    public function __construct(public int $startYear, public int $startMonth) {}

    /**
     * Resolve the fiscal year containing the date (Bangladesh FY starts in July by default).
     */
    public static function for(CarbonInterface $date, int $startMonth = 7): self
    {
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return new self($startYear, $startMonth);
    }

    public function endYear(): int
    {
        return $this->startMonth === 1 ? $this->startYear : $this->startYear + 1;
    }

    /**
     * Two-digit code used by the {yy} sequence token, e.g. "27" for FY 2026-27.
     */
    public function shortCode(): string
    {
        return substr((string) $this->endYear(), -2);
    }

    public function longCode(): string
    {
        return (string) $this->endYear();
    }

    public function label(): string
    {
        return $this->startMonth === 1
            ? (string) $this->startYear
            : $this->startYear.'-'.$this->shortCode();
    }
}
