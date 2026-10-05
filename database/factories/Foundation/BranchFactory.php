<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('BR???')),
            'name' => fake()->city().' Office',
            'sort_order' => 0,
            'is_active' => true,
            'is_head_office' => false,
        ];
    }
}
