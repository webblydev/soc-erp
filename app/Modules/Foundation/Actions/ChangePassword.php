<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;

class ChangePassword
{
    public function handle(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
        ])->save();
    }
}
