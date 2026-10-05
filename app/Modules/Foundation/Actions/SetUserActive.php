<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Activates or deactivates an account. A deactivated user is logged out on their next
 * request by EnsureUserIsActive (spec D10).
 */
class SetUserActive
{
    public function __construct(private EnsureNotLastSuperAdmin $ensureNotLastSuperAdmin) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, bool $active, User $actor): void
    {
        if (! $active) {
            $this->ensureCanDeactivate($user, $actor);
        }

        $user->update(['is_active' => $active]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanDeactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => __('You cannot deactivate your own account.')]);
        }

        $this->ensureNotLastSuperAdmin->handle($user);
    }
}
