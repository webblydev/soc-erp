<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->firstOrCreate(['code' => 'HO'], [
            'name' => 'Head Office', 'is_head_office' => true, 'is_system' => true, 'sort_order' => 1,
        ]);
    }
}
