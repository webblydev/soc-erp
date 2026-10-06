<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\EmployeeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeeType>
 */
class EmployeeTypeFactory extends Factory
{
    protected $model = EmployeeType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('ETY????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
