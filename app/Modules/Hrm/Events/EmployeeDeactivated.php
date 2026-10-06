<?php

namespace App\Modules\Hrm\Events;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An employee left (docs/09 §6): Foundation switches off the login, Projects (04) flags
 * reassignments. Fired inside the exit transaction.
 */
class EmployeeDeactivated
{
    use Dispatchable;

    public function __construct(public Employee $employee, public User $actor) {}
}
