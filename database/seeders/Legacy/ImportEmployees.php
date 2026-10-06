<?php

namespace Database\Seeders\Legacy;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Models\Gender;
use App\Modules\Hrm\Models\MaritalStatus;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * v1 employees (tbl_employee) → employees with JOINED / RESIGNED events (legacy seed spec L5).
 */
class ImportEmployees
{
    private const GENDERS = ['male' => 'MALE', 'female' => 'FEMALE'];

    private const MARITAL = ['married' => 'MARRIED', 'unmarred' => 'SINGLE', 'unmarried' => 'SINGLE', 'single' => 'SINGLE'];

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $companyPhone = (string) CompanyProfile::current()?->phone;

        foreach ($context->legacy()->table('tbl_employee')->orderBy('id')->get() as $row) {
            $existing = Employee::withTrashed()->where('legacy_employee_id', $row->id)->value('id');

            if ($existing !== null) {
                $context->employees[(int) $row->id] = (int) $existing;

                continue;
            }

            $context->employees[(int) $row->id] = $this->create($context, $row, $companyPhone)->id;
            $created++;
        }

        return $created;
    }

    private function create(LegacyContext $context, object $row, string $companyPhone): Employee
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $row->name)) ?: 'Unnamed';
        $lastSpace = strrpos($name, ' ');
        $phone = Phone::normalise((string) $row->phone);
        $notes = [];

        if (! Phone::isValid($phone)) {
            $notes[] = 'v1 phone: '.trim((string) $row->phone);
            $phone = $companyPhone;
        }

        $joined = LegacyMap::date($row->added_date) ?? today()->toDateString();
        $left = $row->status === 'd' ? max($joined, LegacyMap::date($row->update_date ?? null) ?? $joined) : null;
        $designationId = $context->idFor(Designation::class, LegacyMap::POSTS[(int) $row->post_id] ?? 'PROJECT_ENGINEER');
        $departmentId = $context->idFor(Department::class, LegacyMap::DEPARTMENTS[(int) $row->department_id] ?? 'PROJECT_OPS');
        $email = filter_var(trim((string) $row->email), FILTER_VALIDATE_EMAIL) ? Str::lower(trim((string) $row->email)) : null;
        $dob = LegacyMap::date($row->dob ?? null);

        $employee = new Employee;
        $employee->forceFill([
            'employee_code' => $this->code((string) $row->code, (int) $row->id),
            'first_name' => $lastSpace === false ? $name : substr($name, 0, $lastSpace),
            'last_name' => $lastSpace === false ? null : substr($name, $lastSpace + 1),
            'full_name' => $name,
            'father_name' => $this->text($row->father_name ?? null),
            'mother_name' => $this->text($row->mother_name ?? null),
            'gender_id' => $context->idFor(Gender::class, self::GENDERS[Str::lower(trim((string) $row->gender))] ?? ''),
            'date_of_birth' => $dob !== null && $dob < today()->toDateString() ? $dob : null,
            'marital_status_id' => $context->idFor(MaritalStatus::class, self::MARITAL[Str::lower(trim((string) $row->marital_status))] ?? ''),
            'phone' => $phone,
            'official_email' => $email,
            'present_address' => $this->text($row->present_address ?? null),
            'permanent_address' => $this->text($row->permanent_address ?? null),
            'reference' => $this->text($row->reference ?? null),
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'employee_type_id' => $context->idFor(EmployeeType::class, 'PERMANENT'),
            'employee_status_id' => $context->idFor(EmployeeStatus::class, $left === null ? EmployeeStatus::ACTIVE : EmployeeStatus::RESIGNED),
            'joining_date' => $joined,
            'exit_date' => $left,
            'exit_reason_id' => $left === null ? null : $context->idFor(ExitReason::class, 'OTHER'),
            'notes' => $notes === [] ? null : implode("\n", $notes),
            'legacy_employee_id' => (int) $row->id,
            'created_at' => LegacyMap::dateTime($row->added_date) ?? now(),
        ])->save();

        $employee->events()->create([
            'employment_event_type_id' => $context->idFor(EmploymentEventType::class, EmploymentEventType::JOINED),
            'effective_date' => $joined,
            'to_department_id' => $departmentId,
            'to_designation_id' => $designationId,
        ]);

        if ($left !== null) {
            $employee->events()->create([
                'employment_event_type_id' => $context->idFor(EmploymentEventType::class, EmploymentEventType::RESIGNED),
                'effective_date' => $left,
                'note' => 'Inactive in v1',
            ]);
        }

        return $employee;
    }

    /**
     * The v1 code, made unique if another employee already holds it.
     */
    private function code(string $code, int $legacyId): string
    {
        $code = Str::upper(trim($code)) ?: sprintf('E%05d', $legacyId);

        return Employee::withTrashed()->where('employee_code', $code)->exists() ? $code.'-V1' : $code;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
