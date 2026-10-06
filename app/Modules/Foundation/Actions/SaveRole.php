<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a role and its permission grants (docs/01 §5.4).
 * super_admin is never edited; system role codes never change. A non-super-admin actor may
 * only add permissions they hold themselves.
 */
class SaveRole
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, User $actor, ?Role $role = null): Role
    {
        $role ??= new Role;

        if ($role->exists && $role->code === Role::SUPER_ADMIN) {
            throw ValidationException::withMessages(['role' => __('The super admin role cannot be edited.')]);
        }

        /** @var array{name: string, code: string, description?: string|null, is_active?: bool, permissions?: list<string>} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ])->validate();

        if ($role->exists && $role->is_system && $data['code'] !== $role->code) {
            throw ValidationException::withMessages(['code' => __('System role codes cannot be changed.')]);
        }

        return DB::transaction(function () use ($role, $data, $actor): Role {
            $this->ensureActorHolds($role, $data['permissions'] ?? [], $actor);

            $role->fill(Arr::only($data, ['name', 'code', 'description']));
            $role->is_active = (bool) ($data['is_active'] ?? true);
            $role->save();

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensureActorHolds(Role $role, array $permissions, User $actor): void
    {
        if ($actor->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        $current = $role->exists ? $role->permissions()->pluck('name')->all() : [];
        $added = array_diff($permissions, $current);

        if (array_diff($added, $this->registrar->permissionsFor($actor)) !== []) {
            throw ValidationException::withMessages(['permissions' => __('You can only add permissions you hold.')]);
        }
    }
}
