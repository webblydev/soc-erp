<?php

namespace Database\Factories\Crm;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerContact>
 */
class CustomerContactFactory extends Factory
{
    protected $model = CustomerContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'phone' => '018'.fake()->unique()->numerify('########'),
            'is_primary' => false,
        ];
    }
}
