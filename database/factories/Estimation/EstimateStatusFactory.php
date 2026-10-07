<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\EstimateStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EstimateStatus>
 */
class EstimateStatusFactory extends Factory
{
    protected $model = EstimateStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('ES????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
