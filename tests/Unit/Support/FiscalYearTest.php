<?php

use App\Support\FiscalYear;
use Carbon\CarbonImmutable;

test('july starts the next bangladesh fiscal year', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-07-01'));

    expect($fiscalYear->startYear)->toBe(2026)
        ->and($fiscalYear->shortCode())->toBe('27')
        ->and($fiscalYear->longCode())->toBe('2027')
        ->and($fiscalYear->label())->toBe('2026-27');
});

test('june belongs to the fiscal year that started the previous july', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-06-30'));

    expect($fiscalYear->shortCode())->toBe('26')
        ->and($fiscalYear->label())->toBe('2025-26');
});

test('a january start month makes the fiscal year the calendar year', function () {
    $fiscalYear = FiscalYear::for(CarbonImmutable::parse('2026-03-01'), 1);

    expect($fiscalYear->shortCode())->toBe('26')
        ->and($fiscalYear->label())->toBe('2026');
});
