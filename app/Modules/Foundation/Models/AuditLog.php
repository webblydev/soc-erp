<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $impersonator_id
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read User|null $impersonator
 */
#[Fillable(['user_id', 'impersonator_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'url', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The super admin who was signed in as the user when the entry was written.
     *
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * Who acted: the username, "via" the impersonator when there was one.
     */
    public function actorLabel(): string
    {
        $user = $this->user;
        $impersonator = $this->impersonator;
        $label = $user instanceof User ? $user->username : __('system');

        if ($this->impersonator_id !== null) {
            $label .= ' '.__('via :username', ['username' => $impersonator instanceof User ? $impersonator->username : '#'.$this->impersonator_id]);
        }

        return $label;
    }

    /**
     * Every field the entry touched, with its old and new value formatted for display.
     *
     * @return array<string, array{old: string, new: string}>
     */
    public function fieldChanges(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changes = [];

        foreach (array_keys([...$old, ...$new]) as $field) {
            $changes[$field] = [
                'old' => self::displayValue($old[$field] ?? null),
                'new' => self::displayValue($new[$field] ?? null),
            ];
        }

        return $changes;
    }

    private static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
