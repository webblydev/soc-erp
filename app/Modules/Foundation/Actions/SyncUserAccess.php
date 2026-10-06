<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use Illuminate\Validation\ValidationException;

/**
 * Applies a user's roles and direct permissions. Only a super admin may grant or remove
 * super_admin, and the last super admin keeps it (FD-BR-02). Other actors cannot change their
 * own access, and may only assign roles and direct permissions they already hold.
 */
class SyncUserAccess
{
    public function __construct(
        private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin,
        private PermissionRegistrar $registrar,
    ) {}

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

        $this->ensureActorMayDelegate($user, $roles, $permissions, $actor);

        $user->syncRoles($roles);
        $user->syncDirectPermissions($permissions);
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensureActorMayDelegate(User $user, array $roles, array $permissions, User $actor): void
    {
        if ($actor->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $currentRoles = $user->exists ? $user->roles()->pluck('code')->all() : [];
        $currentPermissions = $user->exists ? $user->directPermissions()->pluck('name')->all() : [];

        if ($user->is($actor) && (! self::sameSet($roles, $currentRoles) || ! self::sameSet($permissions, $currentPermissions))) {
            throw ValidationException::withMessages(['roles' => __('You cannot change your own access.')]);
        }

        $held = $this->registrar->permissionsFor($actor);

        $addedRoles = Role::query()->whereIn('code', array_diff($roles, $currentRoles))->with('permissions:id,name')->get();

        foreach ($addedRoles as $role) {
            if (array_diff($role->permissions->pluck('name')->all(), $held) !== []) {
                throw ValidationException::withMessages(['roles' => __('You can only assign roles whose permissions you hold.')]);
            }
        }

        if (array_diff(array_diff($permissions, $currentPermissions), $held) !== []) {
            throw ValidationException::withMessages(['permissions' => __('You can only grant permissions you hold.')]);
        }
    }

    /**
     * @param  array<mixed>  $first
     * @param  array<mixed>  $second
     */
    private static function sameSet(array $first, array $second): bool
    {
        return array_diff($first, $second) === [] && array_diff($second, $first) === [];
    }
}
