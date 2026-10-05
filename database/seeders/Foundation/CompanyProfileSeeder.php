<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanyProfile::query()->exists()) {
            return;
        }

        CompanyProfile::query()->create([
            'name' => 'SOC Consultant & Development Ltd',
            'short_name' => 'SOC',
            'base_currency_id' => Currency::query()->where('code', 'BDT')->value('id'),
            'fiscal_year_start_month' => 7,
        ]);
    }
}
