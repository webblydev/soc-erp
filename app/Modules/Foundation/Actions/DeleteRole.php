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
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
        }

        $holders = $role->users()->count();

        if ($holders > 0) {
            throw ValidationException::withMessages(['role' => trans_choice('Remove this role from its :count user first.|Remove this role from its :count users first.', $holders, ['count' => $holders])]);
        }

        DB::transaction(fn () => $role->delete());
    }
}
