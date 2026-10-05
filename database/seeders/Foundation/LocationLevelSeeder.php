<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Seeder;

class LocationLevelSeeder extends Seeder
{
    private const NAMES = [
        LocationLevel::DIVISION => 'Division',
        LocationLevel::DISTRICT => 'District',
        LocationLevel::THANA => 'Thana / Upazila',
        LocationLevel::AREA => 'Area / Mouza / Sector',
    ];

    public function run(): void
    {
        foreach (LocationLevel::ORDER as $index => $code) {
            LocationLevel::query()->firstOrCreate(['code' => $code], [
                'name' => self::NAMES[$code], 'sort_order' => $index + 1, 'is_system' => true,
            ]);
        }
    }
}
