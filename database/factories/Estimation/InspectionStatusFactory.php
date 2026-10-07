<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\InspectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InspectionStatus>
 */
class InspectionStatusFactory extends Factory
{
    protected $model = InspectionStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('IS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
