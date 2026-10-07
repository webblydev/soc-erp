<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskPriority>
 */
class TaskPriorityFactory extends Factory
{
    protected $model = TaskPriority::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('TP????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
