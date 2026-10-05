<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Concerns\ValidatesUserInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Admin edit of an account. A username change after first login is allowed for admins and
 * audited by the Auditable observer (FD-BR-01). Setting a password forces a change at next login.
 */
class UpdateUser
{
    use ValidatesUserInput;

    public function __construct(
        private SyncUserAccess $syncUserAccess,
        private SetUserActive $setUserActive,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input, User $actor): User
    {
        /** @var array{name: string, username: string, email: ?string, phone: ?string, branch_id: ?int, roles: list<string>, permissions?: list<string>, password: ?string, is_active?: bool} $data */
        $data = Validator::make($this->prepareUserInput($input), $this->userRules($user))->validate();
        $active = (bool) ($data['is_active'] ?? $user->is_active);

        if ($user->is_active && ! $active) {
            $this->setUserActive->ensureCanDeactivate($user, $actor);
        }

        return DB::transaction(function () use ($user, $data, $active, $actor): User {
            $user->fill(Arr::only($data, ['name', 'username', 'email', 'phone', 'branch_id']));
            $user->is_active = $active;

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }

            $user->save();

            $this->syncUserAccess->handle($user, $data['roles'], $data['permissions'] ?? [], $actor);

            return $user;
        });
    }
}
