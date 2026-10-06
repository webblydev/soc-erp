<?php

namespace Database\Seeders\Legacy;

use App\Models\User;
use App\Modules\Foundation\Models\Role;
use App\Modules\Hrm\Models\Employee;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * v1 logins (tbl_user) → users with the dev password `password` (legacy seed spec L6). A v1
 * username that already exists (e.g. the seeded `admin`) maps to that user, left unchanged.
 */
class ImportUsers
{
    public const DEV_PASSWORD = 'password';

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $claimedEmployees = [];

        foreach ($context->legacy()->table('tbl_user')->orderBy('id')->get() as $row) {
            $username = trim((string) $row->user_name);
            $employeeId = $context->employees[(int) $row->employee_id] ?? null;

            // An employee belongs to the first v1 user pointing at it (users.employee_id is unique).
            if ($employeeId !== null && in_array($employeeId, $claimedEmployees, true)) {
                $employeeId = null;
            }

            if ($employeeId !== null) {
                $claimedEmployees[] = $employeeId;
            }

            $existing = User::withTrashed()->whereRaw('LOWER(username) = ?', [Str::lower($username)])->first();

            if ($existing === null) {
                $existing = $this->create($row, $username, $employeeId);
                $created++;
            }

            $context->users[(int) $row->id] = $existing->id;
            $context->usernames[Str::lower($username)] = $existing->id;
        }

        return $created;
    }

    private function create(object $row, string $username, ?int $employeeId): User
    {
        $email = Str::lower(trim((string) $row->email));
        $phone = Phone::normalise((string) $row->phone);

        $user = new User;
        $user->forceFill([
            'name' => trim((string) $row->name) ?: $username,
            'username' => $username,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) && ! User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists() ? $email : null,
            'phone' => $phone !== null && preg_match(Phone::MOBILE, $phone) === 1 ? $phone : null,
            'password' => self::DEV_PASSWORD,
            'employee_id' => $employeeId,
            'is_active' => $row->status !== 'd',
            'must_change_password' => false,
        ])->save();

        $user->syncRoles([$this->role($row, $employeeId)]);

        return $user;
    }

    /**
     * Admin → super_admin, team manager → sales_manager, user → by the employee's department.
     */
    private function role(object $row, ?int $employeeId): string
    {
        return match ($row->type) {
            'a' => Role::SUPER_ADMIN,
            't' => 'sales_manager',
            default => LegacyMap::USER_ROLES[(string) Employee::query()->whereKey($employeeId)->with('department:id,code')->first()?->department->code] ?? 'sales_executive',
        };
    }
}
