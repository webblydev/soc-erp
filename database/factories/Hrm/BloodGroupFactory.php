<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\BloodGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BloodGroup>
 */
class BloodGroupFactory extends Factory
{
    protected $model = BloodGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('BLD????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
