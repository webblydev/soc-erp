<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\CustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerType>
 */
class CustomerTypeFactory extends Factory
{
    protected $model = CustomerType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('CTY????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
