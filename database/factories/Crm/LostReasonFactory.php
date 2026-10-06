<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\LostReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LostReason>
 */
class LostReasonFactory extends Factory
{
    protected $model = LostReason::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('LST????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
