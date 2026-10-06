<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * The materials SOC used in v1 estimates (tbl_material, docs/11 §4.2), without the two test rows.
 */
class MaterialSeeder extends Seeder
{
    /**
     * name → [category code, unit code]
     */
    private const MATERIALS = [
        'Grey Cement (OPC)' => ['CEMENT', 'bag'],
        'Grey Cement (PCC)' => ['CEMENT', 'bag'],
        'Sylhet Sand (FM-2.5)' => ['SAND', 'cft'],
        'Local Sand (FM-2.0)' => ['SAND', 'cft'],
        'Local Sand (FM-1.5)' => ['SAND', 'cft'],
        'Single Stone Chips' => ['STONE_AGGREGATE', 'cft'],
        'Stone Chips (LC)' => ['STONE_AGGREGATE', 'cft'],
        'Stone Chips (Vutu Bhanga)' => ['STONE_AGGREGATE', 'cft'],
    ];

    public function run(): void
    {
        $categories = MaterialCategory::query()->pluck('id', 'code');
        $units = Unit::query()->pluck('id', 'code');
        $number = (int) Material::query()->count();

        foreach (self::MATERIALS as $name => [$category, $unit]) {
            if (Material::query()->where('name', $name)->exists()) {
                continue;
            }

            do {
                $code = sprintf('MAT-%04d', ++$number);
            } while (Material::query()->where('code', $code)->exists());

            Material::query()->create([
                'code' => $code,
                'name' => $name,
                'material_category_id' => $categories[$category],
                'unit_id' => $units[$unit],
                'is_active' => true,
            ]);
        }
    }
}
