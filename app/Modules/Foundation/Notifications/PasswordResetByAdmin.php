<?php

namespace App\Modules\Foundation\Notifications;

use App\Models\User;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * user.password_reset (docs/01 §9): an admin set a new password on the user's account.
 */
class PasswordResetByAdmin extends PreferenceNotification
{
    public function key(): string
    {
        return 'user.password_reset';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your password was reset'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An administrator set a new password on your account. You must change it when you next sign in.'))
            ->line(__('If you did not expect this, contact your administrator.'))
            ->action(__('Sign in'), route('login'));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Your password was reset'),
            'body' => __('An administrator set a new password on your account.'),
            'url' => null,
        ];
    }
}
