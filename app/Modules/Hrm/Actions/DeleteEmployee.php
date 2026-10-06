<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes an employee record entered by mistake. Employees still linked to a login, managing
 * other employees, or heading a department, branch or business line are refused.
 */
class DeleteEmployee
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, Employee $employee): void
    {
        Gate::forUser($actor)->authorize('delete', $employee);

        if ($employee->user()->exists()) {
            throw ValidationException::withMessages(['employee' => __('Unlink the employee’s user account before deleting.')]);
        }

        if ($employee->reports()->exists()) {
            throw ValidationException::withMessages(['employee' => __('Reassign the employee’s direct reports before deleting.')]);
        }

        $heads = DB::table('departments')->where('head_employee_id', $employee->id)->exists()
            || DB::table('branches')->where('manager_employee_id', $employee->id)->exists()
            || DB::table('business_lines')->where('manager_employee_id', $employee->id)->exists();

        if ($heads) {
            throw ValidationException::withMessages(['employee' => __('Employees heading a department, branch or business line cannot be deleted.')]);
        }

        $employee->delete();
    }
}
