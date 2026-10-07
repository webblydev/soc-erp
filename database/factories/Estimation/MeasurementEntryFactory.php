<?php

namespace Database\Factories\Estimation;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MbDirection;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementEntry>
 */
class MeasurementEntryFactory extends Factory
{
    protected $model = MeasurementEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mb_number' => 'MB-'.fake()->unique()->numerify('#######'),
            'project_id' => Project::factory(),
            'mb_direction_id' => fn (): int => EstimationLookups::mbDirection(MbDirection::CUSTOMER),
            'measured_on' => today()->toDateString(),
            'measured_by' => Employee::factory(),
            'description' => fake()->sentence(4),
            'measurement_formula' => MeasurementFormula::Manual,
            'unit_id' => fn (): int => (int) (Unit::query()->where('code', 'sft')->value('id') ?? Unit::factory()->create()->id),
            'quantity' => 10,
            'rate' => 50,
            'amount' => 500,
            'mb_status_id' => fn (): int => EstimationLookups::mbStatus(MbStatus::RECORDED),
        ];
    }

    /**
     * Measured against a BOQ line, on its project and unit.
     */
    public function forLine(EstimateLine $line): static
    {
        return $this->state(fn (): array => [
            'project_id' => $line->estimate->project_id,
            'estimate_line_id' => $line->id,
            'unit_id' => $line->unit_id,
            'description' => $line->description,
            'rate' => $line->rate ?? 0,
        ]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['mb_status_id' => EstimationLookups::mbStatus($code)]);
    }
}
