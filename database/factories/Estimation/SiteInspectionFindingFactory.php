<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteInspectionFinding>
 */
class SiteInspectionFindingFactory extends Factory
{
    protected $model = SiteInspectionFinding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_inspection_id' => SiteInspection::factory(),
            'project_id' => fn (array $attributes): int => (int) SiteInspection::query()->whereKey($attributes['site_inspection_id'])->value('project_id'),
            'location' => fake()->randomElement(['Ground floor', '1st floor roof', 'Stair core']),
            'description' => fake()->sentence(6),
            'finding_severity_id' => fn (): int => EstimationLookups::severity(FindingSeverity::MEDIUM),
            'finding_status_id' => fn (): int => EstimationLookups::findingStatus(FindingStatus::OPEN),
        ];
    }

    public function forInspection(SiteInspection $inspection): static
    {
        return $this->state(fn (): array => ['site_inspection_id' => $inspection->id, 'project_id' => $inspection->project_id]);
    }

    public function severity(string $code): static
    {
        return $this->state(fn (): array => ['finding_severity_id' => EstimationLookups::severity($code)]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['finding_status_id' => EstimationLookups::findingStatus($code)]);
    }
}
