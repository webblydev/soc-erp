<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Validation\ValidationException;

/**
 * Applies a user's roles and direct permissions. Only a super admin may grant or remove
 * super_admin, and the last super admin keeps it (FD-BR-02).
 */
class SyncUserAccess
{
    public function __construct(private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin) {}

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $roles, array $permissions, User $actor): void
    {
        $hadSuperAdmin = $user->exists && $user->roles()->where('code', Role::SUPER_ADMIN)->exists();
        $wantsSuperAdmin = in_array(Role::SUPER_ADMIN, $roles, true);

        if ($hadSuperAdmin !== $wantsSuperAdmin && ! $actor->hasRole(Role::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['roles' => __('Only a super admin can grant or remove the super admin role.')]);
        }

        if ($hadSuperAdmin && ! $wantsSuperAdmin) {
            $this->ensureNotLastSuperAdmin->handle($user);
        }

        $this->ensureActorMayGrant($user, $permissions, $actor);

        $user->syncRoles(array_values($roles));
        $user->syncDirectPermissions(array_values($permissions));
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensureActorMayGrant(User $user, array $permissions, User $actor): void
    {
        if ($actor->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $current = $user->exists ? $user->directPermissions()->pluck('name')->all() : [];

        foreach (array_diff($permissions, $current) as $name) {
            if (! $actor->hasPermission($name)) {
                throw ValidationException::withMessages(['permissions' => __('You can only grant permissions you hold.')]);
            }
        }
    }
}
