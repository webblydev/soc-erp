<?php

namespace App\Modules\Foundation\Notifications;

use App\Models\User;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * security.login_new_ip (docs/01 §9): a finance user signed in from an IP not seen before.
 */
class NewIpSignIn extends PreferenceNotification
{
    public function __construct(public string $ipAddress, public string $signedInAt)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'security.login_new_ip';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New sign-in to your account'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your account was signed in from :ip at :time.', ['ip' => $this->ipAddress, 'time' => $this->signedInAt]))
            ->line(__('If this was not you, change your password and tell your administrator.'))
            ->action(__('Review your profile'), route('profile.edit'));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('New sign-in from :ip', ['ip' => $this->ipAddress]),
            'body' => __('Your account was signed in from a new IP address at :time.', ['time' => $this->signedInAt]),
            'url' => route('profile.edit'),
        ];
    }
}
