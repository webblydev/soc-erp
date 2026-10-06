<?php

use Database\Seeders\Legacy\LegacyMap;

test('v1 dates are cleaned up', function () {
    expect(LegacyMap::date('0000-00-00'))->toBeNull()
        ->and(LegacyMap::date(''))->toBeNull()
        ->and(LegacyMap::date('0026-08-31'))->toBe('2026-08-31')
        ->and(LegacyMap::date('2024-02-03'))->toBe('2024-02-03')
        ->and(LegacyMap::date('not a date'))->toBeNull()
        ->and(LegacyMap::dateTime('2023-09-28 12:16:43')?->toDateTimeString())->toBe('2023-09-28 12:16:43');
});

test('client codes, sources and levels map to v2 codes', function () {
    expect(LegacyMap::businessLineFor('SOC-CON-00012'))->toBe('CON')
        ->and(LegacyMap::businessLineFor('SOC -CON 0001'))->toBe('CON')
        ->and(LegacyMap::businessLineFor('soc-cetp-0100'))->toBe('CETP')
        ->and(LegacyMap::businessLineFor('CETP-2024 -03'))->toBe('CETP')
        ->and(LegacyMap::businessLineFor('SOC-VENDOR -RAJUK-002'))->toBeNull()
        ->and(LegacyMap::sourceFor('F'))->toBe('REFERENCE')
        ->and(LegacyMap::sourceFor('FF'))->toBe('F2F')
        ->and(LegacyMap::sourceFor('X'))->toBe('OTHER')
        ->and(LegacyMap::levelFor('Middle'))->toBe('MID');
});
