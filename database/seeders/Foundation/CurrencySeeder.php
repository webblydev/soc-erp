<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->firstOrCreate(['code' => 'BDT'], [
            'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'is_base' => true,
            'is_system' => true, 'sort_order' => 1,
        ]);

        Currency::query()->firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 2,
        ]);
    }
}
