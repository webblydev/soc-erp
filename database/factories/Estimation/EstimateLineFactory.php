<?php

namespace Database\Factories\Estimation;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 10 × 10 sft line at ৳50 (quantity 100, amount 5,000).
 *
 * @extends Factory<EstimateLine>
 */
class EstimateLineFactory extends Factory
{
    protected $model = EstimateLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_id' => Estimate::factory(),
            'line_no' => '1.01',
            'description' => fake()->sentence(4),
            'measurement_formula' => MeasurementFormula::NosLW,
            'nos' => 1,
            'length' => 10,
            'width' => 10,
            'unit_id' => fn (): int => (int) (Unit::query()->where('code', 'sft')->value('id') ?? Unit::factory()->create()->id),
            'quantity' => 100,
            'rate' => 50,
            'amount' => 5000,
            'sort_order' => 0,
        ];
    }

    /**
     * A manual line with the given quantity and rate.
     */
    public function quantity(string $quantity, string $rate = '50'): static
    {
        return $this->state(fn (): array => [
            'measurement_formula' => MeasurementFormula::Manual,
            'quantity_is_manual' => true,
            'quantity' => $quantity,
            'rate' => $rate,
            'amount' => bcmul($quantity, $rate, 2),
        ]);
    }
}
