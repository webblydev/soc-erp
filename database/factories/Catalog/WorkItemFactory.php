<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Catalog\Models\WorkItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    protected $model = WorkItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('WI-####')),
            'name' => fake()->sentence(4),
            'work_item_category_id' => WorkItemCategory::factory(),
            'unit_id' => Unit::factory(),
            'measurement_formula' => MeasurementFormula::NosLWH,
            'standard_rate' => '100.0000',
            'is_active' => true,
        ];
    }
}
