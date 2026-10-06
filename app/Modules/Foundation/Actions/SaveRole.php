<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a role and its permission grants (docs/01 §5.4).
 * super_admin is never edited; system role codes never change.
 */
class SaveRole
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Role $role = null): Role
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

        return DB::transaction(function () use ($role, $data): Role {
            $role->fill(Arr::only($data, ['name', 'code', 'description']));
            $role->is_active = (bool) ($data['is_active'] ?? true);
            $role->save();

            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }
}
