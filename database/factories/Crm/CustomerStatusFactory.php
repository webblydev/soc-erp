<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerStatus>
 */
class CustomerStatusFactory extends Factory
{
    protected $model = CustomerStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('CST????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['is_blocked' => true]);
    }
}
