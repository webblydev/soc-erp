<?php

namespace App\Modules\Hrm\Services\ExitChecks;

use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\ExitCheckItem;

/**
 * The employee's active login is switched off on exit (docs/09 §4.4, spec H7).
 */
class LinkedUserCheck implements EmployeeExitCheck
{
    public function check(Employee $employee): array
    {
        $user = $employee->user;

        if ($user === null || ! $user->is_active) {
            return [];
        }

        return [new ExitCheckItem(__('Login :username will be deactivated', ['username' => $user->username]), route('admin.users.edit', $user))];
    }
}
