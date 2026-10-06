<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes the link between an employee and their login (spec H8). The login itself is kept.
 */
class UnlinkUser
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Employee $employee): void
    {
        Gate::forUser($actor)->authorize('admin.users.update');

        User::query()->where('employee_id', $employee->id)->get()
            ->each(fn (User $user) => $user->forceFill(['employee_id' => null])->save());
    }
}
