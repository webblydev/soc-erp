<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectRole>
 */
class ProjectRoleFactory extends Factory
{
    protected $model = ProjectRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('PR????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
