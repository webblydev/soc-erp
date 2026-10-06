<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\LeadPriority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadPriority>
 */
class LeadPriorityFactory extends Factory
{
    protected $model = LeadPriority::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('PRI????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
