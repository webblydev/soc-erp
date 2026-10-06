<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\ExitReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExitReason>
 */
class ExitReasonFactory extends Factory
{
    protected $model = ExitReason::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('EXR????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
