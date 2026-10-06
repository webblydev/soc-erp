<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ActivityType>
 */
class ActivityTypeFactory extends Factory
{
    protected $model = ActivityType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('ACT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
            'icon' => 'phone',
        ];
    }
}
