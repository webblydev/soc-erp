<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * FD-BR-02: at least one active super admin must always exist.
 * Call before deactivating, deleting or removing the super_admin role from a user.
 */
class EnsureNotLastSuperAdmin
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user): void
    {
        if (! $user->is_active || ! $user->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $anotherExists = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('code', Role::SUPER_ADMIN)->where('is_active', true))
            ->exists();

        if (! $anotherExists) {
            throw ValidationException::withMessages([
                'user' => __('This is the last active super admin. Assign another super admin first.'),
            ]);
        }
    }
}
