<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * The company profile with the v1 contact details (tbl_company). Fields an admin already filled
 * are left alone.
 */
class CompanyProfileSeeder extends Seeder
{
    private const CONTACT = [
        'address' => 'H-35, (Plot-1081), Jannat Cottage, Khilbarirtek, Gulshan, Vatara, Dhaka-1212',
        'phone' => '01714678285',
        'email' => 'farid.socbdltd@gmail.com',
    ];

    public function run(): void
    {
        $profile = CompanyProfile::query()->first() ?? CompanyProfile::query()->create([
            'name' => 'SOC Consultant & Development Ltd',
            'short_name' => 'SOC',
            'base_currency_id' => Currency::query()->where('code', 'BDT')->value('id'),
            'fiscal_year_start_month' => 7,
        ]);

        foreach (self::CONTACT as $field => $value) {
            if (blank($profile->getAttribute($field))) {
                $profile->setAttribute($field, $value);
            }
        }

        $profile->save();
    }
}
