<?php

namespace App\Modules\Hrm\Concerns;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Hrm\Services\EmployeeFields;
use App\Support\Lookups\ActiveLookup;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared input handling for CreateEmployee and UpdateEmployee (docs/09 §3.2, §5).
 */
trait ValidatesEmployeeInput
{
    private const EDUCATION_COLUMNS = ['institution', 'degree', 'from_year', 'to_year', 'result'];

    private const EXPERIENCE_COLUMNS = ['company', 'position', 'from_date', 'to_date', 'notes'];

    /**
     * Columns that only RecordEmploymentEvent may change on an existing employee (HR-BR-05).
     */
    public const JOB_FIELDS = ['department_id', 'designation_id', 'gross_salary'];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        $input = array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value, $input);

        foreach (['phone', 'emergency_contact_phone', 'mobile_wallet_no'] as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = Phone::normalise(is_string($input[$key]) ? $input[$key] : null);
            }
        }

        foreach (['personal_email', 'official_email'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = Str::lower(trim($input[$key]));
            }
        }

        if (is_string($input['gross_salary'] ?? null)) {
            $input['gross_salary'] = str_replace(',', '', trim($input['gross_salary']));
        }

        if (is_string($input['employee_code'] ?? null)) {
            $input['employee_code'] = Str::upper(trim($input['employee_code']));
        }

        foreach (['education', 'experience'] as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = array_values(array_map(
                    fn (array $row): array => array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value, $row),
                    is_array($input[$key]) ? $input[$key] : [],
                ));
            }
        }

        return $input;
    }

    /**
     * Drop input the actor may not see or change (spec H2): personal fields and education /
     * experience without full visibility, salary and bank fields without update_salary.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function restrict(User $actor, ?Employee $employee, array $input): array
    {
        $full = $employee !== null ? $actor->can('viewFull', $employee) : $actor->can('hrm.employees.view_full');

        if (! $full) {
            $input = Arr::except($input, [...EmployeeFields::FULL, 'education', 'experience']);
        }

        if (! $actor->can('hrm.employees.update_salary')) {
            $input = Arr::except($input, EmployeeFields::SALARY);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateEmployee(array $input, ?Employee $employee): array
    {
        $validator = Validator::make($input, [
            'employee_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/', Rule::unique('employees', 'employee_code')],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'gender_id' => ['nullable', new ActiveLookup('genders', $employee?->gender_id)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status_id' => ['nullable', new ActiveLookup('marital_statuses', $employee?->marital_status_id)],
            'blood_group_id' => ['nullable', new ActiveLookup('blood_groups', $employee?->blood_group_id)],
            'nid_number' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'nid_number')->ignore($employee?->id)],
            'phone' => ['required', Phone::rule()],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'official_email' => ['nullable', 'email', 'max:150'],
            'present_address' => ['nullable', 'string', 'max:2000'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['nullable', Phone::rule()],
            'reference' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', new ActiveLookup('departments', $employee?->department_id)],
            'designation_id' => ['required', new ActiveLookup('designations', $employee?->designation_id)],
            'employee_type_id' => ['required', new ActiveLookup('employee_types', $employee?->employee_type_id)],
            'employee_status_id' => ['required', new ActiveLookup('employee_statuses', $employee?->employee_status_id, fn (Builder $query) => $query->where('is_exit', false))],
            'branch_id' => ['nullable', new ActiveLookup('branches', $employee?->branch_id)],
            'manager_id' => ['nullable', new AssignableEmployee($employee?->manager_id)],
            'joining_date' => ['required', 'date', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'gross_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'mobile_wallet_no' => ['nullable', Phone::rule()],
            'tin' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'education' => ['array', 'max:20'],
            'education.*.id' => ['nullable', 'integer'],
            'education.*.institution' => ['required', 'string', 'max:200'],
            'education.*.degree' => ['required', 'string', 'max:150'],
            'education.*.from_year' => ['nullable', 'integer', 'between:1950,2100'],
            'education.*.to_year' => ['nullable', 'integer', 'between:1950,2100'],
            'education.*.result' => ['nullable', 'string', 'max:60'],
            'experience' => ['array', 'max:20'],
            'experience.*.id' => ['nullable', 'integer'],
            'experience.*.company' => ['required', 'string', 'max:200'],
            'experience.*.position' => ['required', 'string', 'max:150'],
            'experience.*.from_date' => ['nullable', 'date'],
            'experience.*.to_date' => ['nullable', 'date'],
            'experience.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'employee_code.regex' => __('Use capital letters, digits and dashes only.'),
        ], [
            'department_id' => __('department'),
            'designation_id' => __('designation'),
            'employee_type_id' => __('employee type'),
            'employee_status_id' => __('status'),
            'manager_id' => __('manager'),
            'nid_number' => __('NID'),
            'education.*.institution' => __('institution'),
            'education.*.degree' => __('degree'),
            'experience.*.company' => __('company'),
            'experience.*.position' => __('position'),
        ]);

        /** @var array<string, mixed> $data */
        $data = $validator->validate();

        $this->ensureNoManagerCycle($employee, isset($data['manager_id']) ? (int) $data['manager_id'] : null);

        return $data;
    }

    /**
     * HR-BR-03: not self, and not anyone who already reports (directly or not) to the employee.
     * The visited list stops a loop on bad existing data.
     *
     * @throws ValidationException
     */
    private function ensureNoManagerCycle(?Employee $employee, ?int $managerId): void
    {
        if ($employee === null || $managerId === null) {
            return;
        }

        $visited = [];

        for ($current = $managerId; $current !== null && ! in_array($current, $visited, true); $current = $this->managerOf($current)) {
            if ($current === $employee->id) {
                throw ValidationException::withMessages(['manager_id' => __('An employee cannot report to themselves or to someone who reports to them.')]);
            }

            $visited[] = $current;
        }
    }

    private function managerOf(int $employeeId): ?int
    {
        $managerId = Employee::withTrashed()->whereKey($employeeId)->value('manager_id');

        return $managerId === null ? null : (int) $managerId;
    }

    /**
     * @throws ValidationException
     */
    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        Validator::make(['photo' => $photo], ['photo' => ['image', 'mimes:png,jpg,jpeg', 'max:1024']])->validate();

        return $photo->store('employee-photos', 'public') ?: null;
    }

    /**
     * Keep, update, create and delete child rows from the submitted list.
     *
     * @param  HasMany<covariant \Illuminate\Database\Eloquent\Model, Employee>  $relation
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function syncRows(HasMany $relation, array $rows, array $columns): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $attributes = Arr::only($row, $columns);
            $existing = isset($row['id']) ? (clone $relation)->whereKey((int) $row['id'])->first() : null;

            if ($existing !== null) {
                $existing->update($attributes);
            } else {
                $existing = $relation->create($attributes);
            }

            $keep[] = $existing->getKey();
        }

        (clone $relation)->whereNotIn('id', $keep)->get()->each->delete();
    }
}
