<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $notification_key
 * @property string $channel
 * @property bool $is_enabled
 */
#[Fillable(['user_id', 'notification_key', 'channel', 'is_enabled'])]
class NotificationPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /**
     * Every registered key and channel with the user's choice; missing rows mean enabled.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrixFor(User $user): array
    {
        $saved = static::query()->where('user_id', $user->id)->get()
            ->mapWithKeys(fn (self $row): array => [$row->notification_key.'|'.$row->channel => $row->is_enabled]);

        $matrix = [];

        /** @var array<string, array{label: string, channels: list<string>}> $keys */
        $keys = config('notifications.keys', []);

        foreach ($keys as $key => $definition) {
            foreach ($definition['channels'] as $channel) {
                $matrix[$key][$channel] = (bool) ($saved[$key.'|'.$channel] ?? true);
            }
        }

        return $matrix;
    }
}
