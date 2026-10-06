<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->lexify('u???');

        return [
            'code' => $code,
            'name' => fake()->word(),
            'symbol' => $code,
            'unit_kind_id' => UnitKind::factory(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
