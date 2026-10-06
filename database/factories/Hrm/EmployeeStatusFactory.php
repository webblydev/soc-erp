<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\EmployeeStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeeStatus>
 */
class EmployeeStatusFactory extends Factory
{
    protected $model = EmployeeStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('EST????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
