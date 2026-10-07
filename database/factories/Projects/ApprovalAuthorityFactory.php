<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ApprovalAuthority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApprovalAuthority>
 */
class ApprovalAuthorityFactory extends Factory
{
    protected $model = ApprovalAuthority::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('AA????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
