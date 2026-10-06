<?php

namespace App\Modules\Hrm\Events;

use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new employee exists (docs/09 §6): the profile prompts for a login (spec H8, H17).
 */
class EmployeeCreated
{
    use Dispatchable;

    public function __construct(public Employee $employee) {}
}
