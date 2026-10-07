<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectStatus>
 */
class ProjectStatusFactory extends Factory
{
    protected $model = ProjectStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('PS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
