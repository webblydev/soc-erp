<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\FindingSeverity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FindingSeverity>
 */
class FindingSeverityFactory extends Factory
{
    protected $model = FindingSeverity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('FS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
