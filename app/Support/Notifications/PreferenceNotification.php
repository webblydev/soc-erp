<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Modules\Foundation\Models\NotificationPreference;
use App\Support\Facades\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A queued notification registered under a key in config/notifications.php (docs/01 §3.12).
 * It is sent only on the key's channels that the user has not switched off and that are
 * available: mail needs an email address and notifications.email_enabled; sms has no gateway yet.
 */
abstract class PreferenceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * The key in config/notifications.php.
     */
    abstract public function key(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $preferences = NotificationPreference::matrixFor($notifiable)[$this->key()] ?? [];

        return array_values(array_filter(
            array_keys(array_filter($preferences)),
            fn (string $channel): bool => match ($channel) {
                'mail' => filled($notifiable->email) && (bool) Settings::get('notifications.email_enabled', true),
                'database' => true,
                default => false,
            },
        ));
    }
}
