<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteInspection>
 */
class SiteInspectionFactory extends Factory
{
    protected $model = SiteInspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_number' => 'SI-'.fake()->unique()->numerify('#######'),
            'project_id' => Project::factory(),
            'inspection_type_id' => fn (): int => EstimationLookups::inspectionType(InspectionType::WEEKLY),
            'inspection_date' => today()->toDateString(),
            'contractor_name' => fake()->name(),
            'inspection_status_id' => fn (): int => EstimationLookups::inspectionStatus(InspectionStatus::DRAFT),
        ];
    }

    public function onProject(Project $project): static
    {
        return $this->state(fn (): array => ['project_id' => $project->id]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['inspection_status_id' => EstimationLookups::inspectionStatus($code)]);
    }
}
