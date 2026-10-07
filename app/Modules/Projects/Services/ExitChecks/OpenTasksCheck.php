<?php

namespace App\Modules\Projects\Services\ExitChecks;

use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\ExitCheckItem;
use App\Modules\Projects\Models\Task;

/**
 * Open tasks assigned to a leaving employee, to reassign (docs/09 §4.4, Projects spec P22).
 */
class OpenTasksCheck implements EmployeeExitCheck
{
    public function check(Employee $employee): array
    {
        $count = Task::query()->open()->where('assignee_employee_id', $employee->id)->count();

        if ($count === 0) {
            return [];
        }

        return [new ExitCheckItem(
            trans_choice(':count open task to reassign|:count open tasks to reassign', $count),
            route('projects.tasks.index', ['preset' => 'all', 'filters' => ['assignee' => (string) $employee->id]]),
        )];
    }
}
