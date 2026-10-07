<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ProjectServiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectServiceStatus>
 */
class ProjectServiceStatusFactory extends Factory
{
    protected $model = ProjectServiceStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('PSS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
