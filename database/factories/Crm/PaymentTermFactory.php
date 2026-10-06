<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTerm>
 */
class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('PAY????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
            'days' => 0,
        ];
    }
}
