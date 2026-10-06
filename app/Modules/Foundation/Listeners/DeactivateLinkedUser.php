<?php

namespace App\Modules\Foundation\Listeners;

use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Hrm\Events\EmployeeDeactivated;

/**
 * Switches off the login of an employee who left (docs/01 §8). The HRM exit already does this
 * inside its transaction; this covers any other source of the event.
 */
class DeactivateLinkedUser
{
    public function __construct(private SetUserActive $setUserActive) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $user = $event->employee->user()->first();

        if ($user !== null && $user->is_active) {
            $this->setUserActive->handle($user, false, $event->actor);
        }
    }
}
