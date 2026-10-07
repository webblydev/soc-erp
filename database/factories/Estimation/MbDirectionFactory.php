<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\MbDirection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MbDirection>
 */
class MbDirectionFactory extends Factory
{
    protected $model = MbDirection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('MD????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
