<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\InspectionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InspectionType>
 */
class InspectionTypeFactory extends Factory
{
    protected $model = InspectionType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('IT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
