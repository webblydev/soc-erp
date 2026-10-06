<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\PricingBasis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PricingBasis>
 */
class PricingBasisFactory extends Factory
{
    protected $model = PricingBasis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('basis_????'),
            'name' => Str::title(fake()->words(2, true)),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }
}
