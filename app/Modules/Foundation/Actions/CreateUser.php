<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Concerns\ValidatesUserInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Admin-created account (docs/01 §6.1): starts with must_change_password.
 */
class CreateUser
{
    use ValidatesUserInput;

    public function __construct(private SyncUserAccess $syncUserAccess) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, User $actor): User
    {
        /** @var array{name: string, username: string, email: ?string, phone: ?string, branch_id: ?int, roles: list<string>, permissions?: list<string>, password: string, is_active?: bool} $data */
        $data = Validator::make($this->prepareUserInput($input), $this->userRules(null))->validate();

        return DB::transaction(function () use ($data, $actor): User {
            $user = new User(Arr::only($data, ['name', 'username', 'email', 'phone', 'branch_id', 'password']));
            $user->is_active = (bool) ($data['is_active'] ?? true);
            $user->must_change_password = true;
            $user->save();

            $this->syncUserAccess->handle($user, $data['roles'], $data['permissions'] ?? [], $actor);

            return $user;
        });
    }
}
