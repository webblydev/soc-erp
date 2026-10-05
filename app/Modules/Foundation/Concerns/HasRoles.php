<?php

namespace App\Modules\Foundation\Concerns;

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function hasRole(string $code): bool
    {
        return in_array($code, app(PermissionRegistrar::class)->rolesFor($this), true);
    }

    public function hasPermission(string $name): bool
    {
        return in_array($name, app(PermissionRegistrar::class)->permissionsFor($this), true);
    }

    public function assignRole(string $code): void
    {
        $this->syncRoles([...array_values(array_map(strval(...), $this->roles()->pluck('code')->all())), $code]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function syncRoles(array $codes): void
    {
        $codes = array_values(array_unique($codes));
        $roles = Role::query()->whereIn('code', $codes)->get();

        if ($roles->count() !== count($codes)) {
            throw new InvalidArgumentException('Unknown role: '.implode(', ', array_diff($codes, $roles->pluck('code')->all())));
        }

        $this->syncPivot($this->roles(), 'roles', 'roles.id', array_values(array_map(intval(...), $roles->pluck('id')->all())), 'code');
    }

    /**
     * @param  list<string>  $names
     */
    public function syncDirectPermissions(array $names): void
    {
        $names = array_values(array_unique($names));
        $permissions = Permission::query()->whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            throw new InvalidArgumentException('Unknown permission: '.implode(', ', array_diff($names, $permissions->pluck('name')->all())));
        }

        $this->syncPivot($this->directPermissions(), 'permissions', 'permissions.id', array_values(array_map(intval(...), $permissions->pluck('id')->all())), 'name');
    }

    /**
     * @param  BelongsToMany<Role, $this>|BelongsToMany<Permission, $this>  $relation
     * @param  list<int>  $targetIds
     */
    private function syncPivot(BelongsToMany $relation, string $auditKey, string $qualifiedKey, array $targetIds, string $labelColumn): void
    {
        $before = $relation->pluck($labelColumn)->sort()->values()->all();
        $current = $relation->pluck($qualifiedKey)->all();

        $relation->detach(array_values(array_diff($current, $targetIds)));
        $relation->attach(collect(array_diff($targetIds, $current))
            ->mapWithKeys(fn (int $id): array => [$id => ['created_by' => Auth::id(), 'created_at' => now()]])
            ->all());

        $after = $relation->pluck($labelColumn)->sort()->values()->all();

        if ($before !== $after) {
            AuditTrail::record($this, 'updated', [$auditKey => $before], [$auditKey => $after]);
        }

        app(PermissionRegistrar::class)->forget($this);
    }
}
