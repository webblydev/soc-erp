<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\EmployeeDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeeDocumentType>
 */
class EmployeeDocumentTypeFactory extends Factory
{
    protected $model = EmployeeDocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('EDT????')),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
