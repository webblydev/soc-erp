<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\FindingCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FindingCategory>
 */
class FindingCategoryFactory extends Factory
{
    protected $model = FindingCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('FC????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
