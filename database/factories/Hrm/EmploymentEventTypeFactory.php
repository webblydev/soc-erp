<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmploymentEventType>
 */
class EmploymentEventTypeFactory extends Factory
{
    protected $model = EmploymentEventType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('EVT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
