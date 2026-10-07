<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\EstimateKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EstimateKind>
 */
class EstimateKindFactory extends Factory
{
    protected $model = EstimateKind::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('EK????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
