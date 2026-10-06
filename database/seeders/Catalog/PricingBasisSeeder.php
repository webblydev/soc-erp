<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\PricingBasis;
use Illuminate\Database\Seeder;

/**
 * Pricing bases (docs/02 §3.3). System rows: code reads their codes.
 */
class PricingBasisSeeder extends Seeder
{
    private const BASES = [
        PricingBasis::FIXED => 'Fixed',
        PricingBasis::PER_UNIT => 'Per unit',
        PricingBasis::PERCENT_OF_COST => 'Percent of cost',
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::BASES as $code => $name) {
            PricingBasis::query()->firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ++$order, 'is_system' => true]);
        }
    }
}
