<?php

namespace App\Modules\Hrm\Contracts;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\ExitCheckItem;

/**
 * Something to resolve or know before an employee leaves (docs/09 §4.4, spec H7). Projects (04)
 * adds open tasks and PM roles, Accounting (08) open advances.
 */
interface EmployeeExitCheck
{
    /**
     * @return list<ExitCheckItem>
     */
    public function check(Employee $employee): array;
}
