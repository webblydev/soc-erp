<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\UnitKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitKind>
 */
class UnitKindFactory extends Factory
{
    protected $model = UnitKind::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('kind_????'),
            'name' => Str::title(fake()->word()),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
