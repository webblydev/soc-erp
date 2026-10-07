<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApprovalStatus>
 */
class ApprovalStatusFactory extends Factory
{
    protected $model = ApprovalStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('AS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
