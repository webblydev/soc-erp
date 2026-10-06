<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaterialCategory>
 */
class MaterialCategoryFactory extends Factory
{
    protected $model = MaterialCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('CAT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
