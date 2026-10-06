<?php

namespace Database\Factories\Crm;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_number' => 'C-'.fake()->unique()->numerify('######'),
            'customer_type_id' => CustomerType::factory(),
            'name' => fake()->name(),
            'phone' => '017'.fake()->unique()->numerify('########'),
            'customer_status_id' => fn (): int => CustomerStatus::query()->where('code', CustomerStatus::ACTIVE)->value('id')
                ?? CustomerStatus::factory()->create()->id,
        ];
    }

    public function managedBy(User $user): static
    {
        return $this->state(fn (): array => ['account_manager_user_id' => $user->id]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => [
            'customer_status_id' => CustomerStatus::query()->where('code', CustomerStatus::BLOCKED)->value('id')
                ?? CustomerStatus::factory()->blocked()->create()->id,
        ]);
    }
}
