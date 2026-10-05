<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Resolves a user's effective roles and permissions (role grants ∪ direct grants),
 * memoised per request and cached per user until a role or grant changes (docs/00 §6.1).
 */
final class PermissionRegistrar
{
    /** @var array<int, array{roles: list<string>, permissions: list<string>}> */
    private array $resolved = [];

    public function __construct(private CacheRepository $cache) {}

    /**
     * @return list<string>
     */
    public function rolesFor(User $user): array
    {
        return $this->resolve($user)['roles'];
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        return $this->resolve($user)['permissions'];
    }

    public function forget(User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;

        unset($this->resolved[$id]);
        $this->cache->forget($this->cacheKey($id));
    }

    public function forgetRole(Role $role): void
    {
        $role->users()->pluck('users.id')->each(fn (int $id) => $this->forget($id));
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function resolve(User $user): array
    {
        return $this->resolved[$user->id] ??= $this->cache->rememberForever(
            $this->cacheKey($user->id),
            fn (): array => $this->load($user),
        );
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function load(User $user): array
    {
        $roles = $user->roles()->where('is_active', true)->with('permissions:id,name')->get();

        $permissions = $roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->merge($user->directPermissions()->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'roles' => $roles->pluck('code')->sort()->values()->all(),
            'permissions' => $permissions,
        ];
    }

    private function cacheKey(int $userId): string
    {
        return "permissions.user.{$userId}";
    }
}
