<?php

namespace Database\Factories\Hrm;

use App\Models\User;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * is_active_employment and is_exit per seeded status (spec H6).
     */
    private const STATUS_FLAGS = [
        EmployeeStatus::ACTIVE => [true, false],
        EmployeeStatus::ON_LEAVE => [true, false],
        EmployeeStatus::SUSPENDED => [false, false],
        EmployeeStatus::RESIGNED => [false, true],
        EmployeeStatus::TERMINATED => [false, true],
        EmployeeStatus::RETIRED => [false, true],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => "{$first} {$last}",
            'phone' => '018'.fake()->unique()->numerify('########'),
            'department_id' => Department::factory(),
            'designation_id' => Designation::factory(),
            'employee_type_id' => EmployeeType::factory(),
            'employee_status_id' => fn (): int => self::statusId(EmployeeStatus::ACTIVE),
            'joining_date' => today()->subYear()->toDateString(),
        ];
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['employee_status_id' => self::statusId($code)]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn (): array => ['department_id' => $department->id]);
    }

    public function linkedTo(User $user): static
    {
        return $this->afterCreating(fn (Employee $employee) => $user->forceFill(['employee_id' => $employee->id])->save());
    }

    private static function statusId(string $code): int
    {
        [$active, $exit] = self::STATUS_FLAGS[$code] ?? [true, false];

        return (int) (EmployeeStatus::query()->where('code', $code)->value('id')
            ?? EmployeeStatus::factory()->create(['code' => $code, 'name' => ucfirst(strtolower($code)), 'is_active_employment' => $active, 'is_exit' => $exit])->id);
    }
}
