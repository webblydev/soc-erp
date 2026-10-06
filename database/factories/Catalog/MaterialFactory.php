<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('MT-####')),
            'name' => fake()->words(3, true),
            'material_category_id' => MaterialCategory::factory(),
            'unit_id' => Unit::factory(),
            'standard_rate' => '550.0000',
            'is_active' => true,
        ];
    }
}
