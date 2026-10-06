<?php

namespace Database\Factories\Catalog;

use App\Modules\Catalog\Models\BusinessLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessLine>
 */
class BusinessLineFactory extends Factory
{
    protected $model = BusinessLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->lexify('BL???'));

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'project_prefix' => 'SOC-'.$code,
            'is_internal' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function internal(): static
    {
        return $this->state(['is_internal' => true]);
    }
}
