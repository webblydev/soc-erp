<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRole
{
    /**
     * @throws ValidationException
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $locked = Role::query()->whereKey($role->id)->lockForUpdate()->first() ?? $role;

            if ($locked->is_system) {
                throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
            }

            $holders = $locked->users()->count();

            if ($holders > 0) {
                throw ValidationException::withMessages(['role' => trans_choice('Remove this role from its :count user first.|Remove this role from its :count users first.', $holders, ['count' => $holders])]);
            }

            $locked->delete();
        });
    }
}
