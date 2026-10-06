<?php

namespace App\Modules\Hrm\Services;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;

/**
 * Which employee columns a user may see (spec H2): basic for everyone with view_basic, full for
 * view_full (or the employee themselves), salary for view_salary (or the employee themselves).
 */
final class EmployeeFields
{
    public const BASIC = [
        'employee_code', 'full_name', 'first_name', 'last_name', 'photo_path', 'department_id', 'designation_id', 'employee_type_id',
        'employee_status_id', 'branch_id', 'manager_id', 'phone', 'official_email', 'joining_date', 'confirmation_date', 'exit_date', 'exit_reason_id',
    ];

    public const FULL = [
        'father_name', 'mother_name', 'gender_id', 'date_of_birth', 'marital_status_id', 'blood_group_id', 'nid_number', 'personal_email',
        'present_address', 'permanent_address', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone', 'reference', 'tin', 'notes',
    ];

    public const SALARY = ['gross_salary', 'bank_name', 'bank_account_no', 'mobile_wallet_no'];

    /**
     * Columns visible to the user, for one employee or (without one) for employees in general.
     *
     * @return list<string>
     */
    public static function visibleTo(User $user, ?Employee $employee = null): array
    {
        $full = $employee !== null ? $user->can('viewFull', $employee) : $user->can('hrm.employees.view_full');
        $salary = $employee !== null ? $user->can('viewSalary', $employee) : $user->can('hrm.employees.view_salary');

        return [...self::BASIC, ...($full ? self::FULL : []), ...($salary ? self::SALARY : [])];
    }
}
