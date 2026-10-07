<?php

namespace Database\Factories\Projects;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factories run unguarded, so states set number, status and people columns directly.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use ResolvesLookups;

    protected $model = Project::class;

    /**
     * is_open, is_closed, allows_billing, allows_costing per seeded status.
     */
    private const STATUS_FLAGS = [
        ProjectStatus::COMPLETED => ['is_open' => false, 'is_closed' => true],
        ProjectStatus::CANCELLED => ['is_open' => false, 'is_closed' => true, 'allows_billing' => false, 'allows_costing' => false],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_number' => 'PRJ-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name().', '.fake()->city(),
            'customer_id' => Customer::factory(),
            'business_line_id' => BusinessLine::factory(),
            'project_type_id' => fn (): int => self::lookupId(ProjectType::class, ProjectType::RESIDENTIAL),
            'project_status_id' => fn (): int => self::statusId(ProjectStatus::IN_PROGRESS),
            'start_date' => today()->subMonth()->toDateString(),
            'contract_value' => 0,
        ];
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['project_status_id' => self::statusId($code)]);
    }

    public function managedBy(Employee $employee): static
    {
        return $this->state(fn (): array => ['project_manager_id' => $employee->id]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }

    public function internal(): static
    {
        return $this->state(fn (): array => [
            'customer_id' => null,
            'project_type_id' => self::lookupId(ProjectType::class, ProjectType::INTERNAL, ['is_internal' => true, 'is_billable' => false]),
        ]);
    }

    private static function statusId(string $code): int
    {
        return self::lookupId(ProjectStatus::class, $code, self::STATUS_FLAGS[$code] ?? []);
    }
}
