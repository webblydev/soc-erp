<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\NotificationPreference;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreferences
{
    /**
     * @param  array<string, array<string, bool>>  $matrix
     */
    public function handle(User $user, array $matrix): void
    {
        /** @var array<string, array{label: string, channels: list<string>}> $keys */
        $keys = config('notifications.keys', []);

        DB::transaction(function () use ($user, $matrix, $keys): void {
            foreach ($matrix as $key => $channels) {
                foreach ($channels as $channel => $enabled) {
                    if (! in_array($channel, $keys[$key]['channels'] ?? [], true)) {
                        continue;
                    }

                    NotificationPreference::query()->updateOrCreate(
                        ['user_id' => $user->id, 'notification_key' => $key, 'channel' => $channel],
                        ['is_enabled' => (bool) $enabled],
                    );
                }
            }
        });
    }
}
