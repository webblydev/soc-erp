<?php

namespace App\Modules\Foundation\Notifications;

use App\Models\User;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * user.created (docs/01 §9). Names the username and sign-in page; never the password.
 */
class AccountCreated extends PreferenceNotification
{
    public function key(): string
    {
        return 'user.created';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your :app account', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An account was created for you. Your username is :username.', ['username' => $notifiable->username]))
            ->line(__('Your administrator will give you a temporary password, which you must change when you first sign in.'))
            ->action(__('Sign in'), route('login'));
    }
}
