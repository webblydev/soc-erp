<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    protected $model = LeadStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('STS????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
            'probability_pct' => 10,
        ];
    }

    public function won(): static
    {
        return $this->state(fn (): array => ['code' => 'WON'.Str::upper(fake()->unique()->lexify('???')), 'is_won' => true, 'is_closed' => true, 'probability_pct' => 100]);
    }

    public function lost(): static
    {
        return $this->state(fn (): array => ['code' => 'LOST'.Str::upper(fake()->unique()->lexify('???')), 'is_lost' => true, 'is_closed' => true, 'probability_pct' => 0]);
    }
}
