<?php

namespace App\Models;

use App\Modules\Foundation\Concerns\HasRoles;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string|null $phone
 * @property string $password
 * @property int|null $employee_id
 * @property int|null $branch_id
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'employee_id', 'branch_id', 'avatar_path', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes, TracksAuthors;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Attributes never written to the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
            'last_login_at', 'last_login_ip', 'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
