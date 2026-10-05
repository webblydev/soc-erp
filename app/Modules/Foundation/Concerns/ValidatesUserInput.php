<?php

namespace App\Modules\Foundation\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation for admin-maintained user accounts (docs/01 §5.3).
 */
trait ValidatesUserInput
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function userRules(?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', $this->uniqueIgnoringCase('username', $user)],
            'email' => ['nullable', 'string', 'email', 'max:150', $this->uniqueIgnoringCase('email', $user)],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'code')->where('is_active', true)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'password' => [$user === null ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Trim and blank-to-null the free-text fields and strip phone separators before validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function prepareUserInput(array $input): array
    {
        $phone = (string) preg_replace('/[\s\-()]/', '', (string) ($input['phone'] ?? ''));

        return [
            ...$input,
            'username' => trim((string) ($input['username'] ?? '')),
            'email' => filled($input['email'] ?? null) ? trim((string) $input['email']) : null,
            'phone' => $phone === '' ? null : $phone,
            'branch_id' => filled($input['branch_id'] ?? null) ? (int) $input['branch_id'] : null,
            'password' => filled($input['password'] ?? null) ? $input['password'] : null,
        ];
    }

    private function uniqueIgnoringCase(string $column, ?User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $user): void {
            $taken = User::withTrashed()
                ->whereRaw("LOWER({$column}) = ?", [Str::lower(trim((string) $value))])
                ->when($user !== null, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();

            if ($taken) {
                $fail(__('This :attribute is already taken.'));
            }
        };
    }
}
