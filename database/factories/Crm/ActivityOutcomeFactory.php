<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\ActivityOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ActivityOutcome>
 */
class ActivityOutcomeFactory extends Factory
{
    protected $model = ActivityOutcome::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('OUT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
