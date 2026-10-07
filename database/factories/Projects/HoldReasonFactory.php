<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\HoldReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HoldReason>
 */
class HoldReasonFactory extends Factory
{
    protected $model = HoldReason::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('HR????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
