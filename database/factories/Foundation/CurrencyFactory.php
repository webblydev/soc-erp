<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->word(),
            'symbol' => '¤',
            'decimal_places' => 2,
            'is_base' => false,
            'is_active' => true,
        ];
    }
}
