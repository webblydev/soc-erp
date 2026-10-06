<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes an account. Your own account and the last active super admin are refused (FD-BR-02).
 */
class DeleteUser
{
    public function __construct(private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $this->ensureNotLastSuperAdmin->lockActiveSuperAdmins();
            $this->ensureNotLastSuperAdmin->ensureActorMayChange($user, $actor);

            if ($user->is($actor)) {
                throw ValidationException::withMessages(['user' => __('You cannot delete your own account.')]);
            }

            $this->ensureNotLastSuperAdmin->handle($user);

            $user->delete();
        });
    }
}
