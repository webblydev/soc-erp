<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\WorkItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkItemCategory>
 */
class WorkItemCategoryFactory extends Factory
{
    protected $model = WorkItemCategory::class;

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
