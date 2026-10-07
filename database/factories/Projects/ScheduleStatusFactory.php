<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ScheduleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduleStatus>
 */
class ScheduleStatusFactory extends Factory
{
    protected $model = ScheduleStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('SS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
