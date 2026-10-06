<?php

namespace Database\Factories\Crm;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factories run unguarded, so states set status, assignment and conversion columns directly.
 *
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_number' => 'L-'.fake()->unique()->numerify('######'),
            'lead_date' => today(),
            'name' => fake()->name(),
            'phone' => '019'.fake()->unique()->numerify('########'),
            'lead_source_id' => LeadSource::factory(),
            'lead_status_id' => fn (): int => $this->statusId(LeadStatus::NEW),
            'lead_priority_id' => fn (): int => LeadPriority::query()->where('code', LeadPriority::NORMAL)->value('id')
                ?? LeadPriority::factory()->create()->id,
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => ['assigned_to' => $user->id, 'assigned_at' => now()]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => [
            'lead_status_id' => $this->statusId($code),
            ...($code === LeadStatus::LOST ? ['lost_at' => now()] : []),
        ]);
    }

    public function converted(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'lead_status_id' => $this->statusId(LeadStatus::WON),
            'converted_customer_id' => $customer->id,
            'converted_at' => now(),
            'won_at' => now(),
        ]);
    }

    /**
     * The seeded status row, or a matching factory row when the CRM seeder has not run.
     */
    private function statusId(string $code): int
    {
        $id = LeadStatus::query()->where('code', $code)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $factory = LeadStatus::factory();

        $status = match ($code) {
            LeadStatus::WON => $factory->won()->create(['code' => LeadStatus::WON]),
            LeadStatus::LOST => $factory->lost()->create(['code' => LeadStatus::LOST]),
            default => $factory->create(['code' => $code]),
        };

        return $status->id;
    }
}
