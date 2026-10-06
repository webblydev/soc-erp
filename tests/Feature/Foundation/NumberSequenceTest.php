<?php

use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    NumberSequenceFormat::query()->create(['document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year']);
    NumberSequenceFormat::query()->create(['document_type' => 'lead', 'format' => 'L-{seq:6}', 'reset_policy' => 'never']);
    NumberSequenceFormat::query()->create(['document_type' => 'project', 'format' => '{bl_prefix}-{seq:4}', 'reset_policy' => 'never', 'scope_by' => 'business_line']);

    $this->travelTo('2026-10-05 10:00:00');
});

function nextNumber(string $type, array $context = []): string
{
    return DB::transaction(fn () => app(NumberSequenceService::class)->next($type, $context));
}

test('fiscal year sequences render the fy code and increment', function () {
    expect(nextNumber('invoice'))->toBe('INV-27-00001')
        ->and(nextNumber('invoice'))->toBe('INV-27-00002');
});

test('global sequences never reset', function () {
    expect(nextNumber('lead'))->toBe('L-000001');

    $this->travelTo('2027-08-01');

    expect(nextNumber('lead'))->toBe('L-000002');
});

test('fiscal year sequences restart in a new fiscal year', function () {
    $this->travelTo('2027-06-30');
    expect(nextNumber('invoice'))->toBe('INV-27-00001');

    $this->travelTo('2027-07-01');
    expect(nextNumber('invoice'))->toBe('INV-28-00001');
});

test('the context date decides the fiscal year', function () {
    expect(nextNumber('invoice', ['date' => '2026-05-01']))->toBe('INV-26-00001');
});

test('business line sequences count separately per prefix', function () {
    expect(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0001')
        ->and(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0002')
        ->and(nextNumber('project', ['bl_prefix' => 'SOC-INT']))->toBe('SOC-INT-0001');
});

test('migrated sequences continue from their next number', function () {
    NumberSequence::query()->create([
        'document_type' => 'project', 'scope_key' => 'bl:SOC-BD', 'format' => '{bl_prefix}-{seq:4}',
        'next_number' => 103, 'reset_policy' => 'never',
    ]);

    expect(nextNumber('project', ['bl_prefix' => 'SOC-BD']))->toBe('SOC-BD-0103');
});

test('numbers wider than the padding are not truncated', function () {
    NumberSequence::query()->create([
        'document_type' => 'lead', 'scope_key' => '', 'format' => 'L-{seq:6}',
        'next_number' => 1000000, 'reset_policy' => 'never',
    ]);

    expect(nextNumber('lead'))->toBe('L-1000000');
});

test('business line scope requires a prefix', function () {
    nextNumber('project');
})->throws(InvalidArgumentException::class);

test('unknown document types are rejected', function () {
    nextNumber('nonsense');
})->throws(InvalidArgumentException::class);

test('issuing numbers writes no audit rows', function () {
    nextNumber('lead');
    nextNumber('lead');

    expect(AuditLog::query()->where('auditable_type', 'number_sequence')->exists())->toBeFalse()
        ->and(NumberSequence::query()->where('document_type', 'lead')->sole()->next_number)->toBe(3);
});

test('the preview clamps the seq width to nine digits', function () {
    $preview = app(NumberSequenceService::class)->preview('X-{seq:2000000000}', 7);

    expect($preview)->toBe('X-000000007');
});
