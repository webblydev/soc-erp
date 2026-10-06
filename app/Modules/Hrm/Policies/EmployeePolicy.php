<?php

namespace App\Modules\Hrm\Policies;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;

/**
 * Employee access (docs/09 §2, spec H2). There is no row scope: everyone with view_basic sees the
 * directory. The linked user sees their own full and salary fields, read-only.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hrm.employees.view_basic');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user) || $this->isSelf($user, $employee);
    }

    public function viewFull(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employees.view_full') || $this->isSelf($user, $employee);
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employees.view_salary') || $this->isSelf($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('hrm.employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employees.update');
    }

    public function updateSalary(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employees.update_salary');
    }

    public function deactivate(User $user, Employee $employee): bool
    {
        return $user->can('hrm.employees.deactivate');
    }

    /**
     * NID scans and the like: documents.view alone is not enough, because the viewer role's
     * `*.view` grant includes it (spec H2).
     */
    public function viewDocuments(User $user, Employee $employee): bool
    {
        return ($user->can('hrm.documents.view') && $this->viewFull($user, $employee)) || $this->isSelf($user, $employee);
    }

    public function manageDocuments(User $user, Employee $employee): bool
    {
        return $user->can('hrm.documents.manage');
    }

    public function manageHistory(User $user, Employee $employee): bool
    {
        return $user->can('hrm.history.manage');
    }

    private function isSelf(User $user, Employee $employee): bool
    {
        return $user->employee_id !== null && $user->employee_id === $employee->id;
    }
}
