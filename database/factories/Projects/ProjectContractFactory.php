<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectContract>
 */
class ProjectContractFactory extends Factory
{
    use ResolvesLookups;

    protected $model = ProjectContract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'agreement_date' => today()->toDateString(),
            'deed_amount' => 0,
            'contract_status_id' => fn (): int => self::lookupId(ContractStatus::class, ContractStatus::DRAFT),
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (): array => ['contract_status_id' => self::lookupId(ContractStatus::class, ContractStatus::SIGNED), 'signed_at' => now()]);
    }
}
