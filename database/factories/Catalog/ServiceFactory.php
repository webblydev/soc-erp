<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('SV-###??')),
            'name' => fake()->words(3, true),
            'service_category_id' => ServiceCategory::factory(),
            'business_line_id' => BusinessLine::factory(),
            'pricing_basis_id' => PricingBasis::factory(),
            'default_rate' => '1000.0000',
            'requires_approval_tracking' => false,
            'is_active' => true,
        ];
    }
}
