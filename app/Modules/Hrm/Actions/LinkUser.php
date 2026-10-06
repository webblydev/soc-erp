<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Links an existing login to an employee (FD-BR-03, spec H8). Neither may already be linked.
 */
class LinkUser
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, User $user): void
    {
        Gate::forUser($actor)->authorize('admin.users.update');

        DB::transaction(function () use ($employee, $user): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->employee_id !== null || User::query()->where('employee_id', $employee->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['user_id' => __('This user or employee is already linked.')]);
            }

            $user->forceFill(['employee_id' => $employee->id])->save();
        });
    }
}
