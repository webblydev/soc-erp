<?php

namespace Database\Factories\Hrm;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_document_type_id' => EmployeeDocumentType::factory(),
            'document_no' => fake()->bothify('??######'),
        ];
    }

    public function expiringOn(string $date): static
    {
        return $this->state(fn (): array => ['expiry_date' => $date]);
    }
}
