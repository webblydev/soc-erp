<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ScheduleTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduleTrigger>
 */
class ScheduleTriggerFactory extends Factory
{
    protected $model = ScheduleTrigger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('ST????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
