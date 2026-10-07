<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factories run unguarded, so states set number, status and totals directly.
 *
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    protected $model = Estimate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_number' => 'EST-'.fake()->unique()->numerify('#######'),
            'estimate_kind_id' => fn (): int => EstimationLookups::kind(EstimateKind::BOQ),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'estimate_date' => today()->toDateString(),
            'revision_no' => 0,
            'estimate_status_id' => fn (): int => EstimationLookups::estimateStatus(EstimateStatus::DRAFT),
            'prepared_by' => Employee::factory(),
            'subtotal' => 0,
            'total_amount' => 0,
        ];
    }

    public function onProject(Project $project): static
    {
        return $this->state(fn (): array => ['project_id' => $project->id]);
    }

    public function ofKind(string $code): static
    {
        return $this->state(fn (): array => ['estimate_kind_id' => EstimationLookups::kind($code)]);
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['estimate_status_id' => EstimationLookups::estimateStatus($code)]);
    }

    public function approved(): static
    {
        return $this->withStatus(EstimateStatus::APPROVED)->state(fn (): array => ['approved_at' => now()]);
    }

    /**
     * A revision of the given estimate's root.
     */
    public function revisionOf(Estimate $previous): static
    {
        return $this->state(fn (): array => [
            'project_id' => $previous->project_id,
            'estimate_kind_id' => $previous->estimate_kind_id,
            'root_estimate_id' => $previous->rootId(),
            'revised_from_id' => $previous->id,
            'revision_no' => $previous->revision_no + 1,
            'estimate_number' => $previous->estimate_number.'-R'.($previous->revision_no + 1),
        ]);
    }

    /**
     * Work lines, with the subtotal and total set to their sum.
     */
    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (Estimate $estimate) use ($count): void {
            EstimateLine::factory()->count($count)->sequence(fn ($sequence): array => [
                'line_no' => '1.'.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT),
                'sort_order' => $sequence->index,
            ])->create(['estimate_id' => $estimate->id]);

            $total = $estimate->lines()->sum('amount');
            $estimate->forceFill(['subtotal' => $total, 'total_amount' => $total])->saveQuietly();
        });
    }
}
