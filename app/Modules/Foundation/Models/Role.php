<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Modules\Foundation\Services\PermissionRegistrar;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\AuditTrail;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Foundation\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'is_system', 'is_active'])]
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, HasFactory, TracksAuthors;

    public const SUPER_ADMIN = 'super_admin';

    protected static function booted(): void
    {
        static::saved(function (Role $role): void {
            if ($role->wasChanged('is_active')) {
                app(PermissionRegistrar::class)->forgetRole($role);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Replace the role's permissions with exactly the given names.
     *
     * @param  list<string>  $names
     */
    public function syncPermissions(array $names): void
    {
        $this->changePermissions($names, detachMissing: true);
    }

    /**
     * Add the given permissions, keeping existing grants.
     *
     * @param  list<string>  $names
     */
    public function grantPermissions(array $names): void
    {
        $this->changePermissions($names, detachMissing: false);
    }

    /**
     * @param  list<string>  $names
     */
    private function changePermissions(array $names, bool $detachMissing): void
    {
        $names = array_values(array_unique($names));
        $permissions = Permission::query()->whereIn('name', $names)->pluck('id', 'name');

        if ($permissions->count() !== count($names)) {
            throw new InvalidArgumentException('Unknown permission: '.implode(', ', array_diff($names, $permissions->keys()->all())));
        }

        $target = $permissions->values();
        $before = $this->permissions()->pluck('name')->sort()->values()->all();
        $current = $this->permissions()->pluck('permissions.id');

        if ($detachMissing) {
            $this->permissions()->detach($current->diff($target)->all());
        }

        $this->permissions()->attach(
            $target->diff($current)->mapWithKeys(fn (int $id): array => [$id => ['created_at' => now()]])->all()
        );

        $after = $this->permissions()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            AuditTrail::record($this, 'updated', ['permissions' => $before], ['permissions' => $after]);
        }

        app(PermissionRegistrar::class)->forgetRole($this);
    }
}
