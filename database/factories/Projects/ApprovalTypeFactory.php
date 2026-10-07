<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ApprovalType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApprovalType>
 */
class ApprovalTypeFactory extends Factory
{
    protected $model = ApprovalType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('AT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
