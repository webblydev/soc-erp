<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use Illuminate\Database\Seeder;

/**
 * Unit kinds (system rows) and the units of docs/02 §3.4.
 */
class UnitSeeder extends Seeder
{
    private const KINDS = ['length' => 'Length', 'area' => 'Area', 'volume' => 'Volume', 'weight' => 'Weight', 'count' => 'Count', 'time' => 'Time', 'lump' => 'Lump'];

    /** @var array<string, array{0: string, 1: string, 2: string}> code => [name, symbol, kind] */
    private const UNITS = [
        'rft' => ['Running foot', 'rft', 'length'],
        'rm' => ['Running metre', 'rm', 'length'],
        'sft' => ['Square foot', 'sft', 'area'],
        'sqm' => ['Square metre', 'sqm', 'area'],
        'cft' => ['Cubic foot', 'cft', 'volume'],
        'cum' => ['Cubic metre', 'cum', 'volume'],
        'nos' => ['Numbers', 'nos', 'count'],
        'kg' => ['Kilogram', 'kg', 'weight'],
        'ton' => ['Ton', 'ton', 'weight'],
        'bag' => ['Bag', 'bag', 'weight'],
        'ltr' => ['Litre', 'ltr', 'volume'],
        'katha' => ['Katha', 'katha', 'area'],
        'decimal' => ['Decimal (land)', 'dec', 'area'],
        'floor' => ['Floor', 'floor', 'count'],
        'job' => ['Job', 'job', 'lump'],
        'ls' => ['Lump sum', 'LS', 'lump'],
        'month' => ['Month', 'month', 'time'],
        'day' => ['Day', 'day', 'time'],
        'participant' => ['Participant', 'participant', 'count'],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::KINDS as $code => $name) {
            UnitKind::query()->firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ++$order, 'is_system' => true]);
        }

        $kinds = UnitKind::query()->pluck('id', 'code');
        $order = 0;

        foreach (self::UNITS as $code => [$name, $symbol, $kind]) {
            Unit::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'symbol' => $symbol, 'unit_kind_id' => $kinds[$kind], 'sort_order' => ++$order,
            ]);
        }
    }
}
