<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * projects.tasks.overdue (docs/04 §10): one daily summary per user of the overdue tasks they work
 * on or manage (spec P19).
 */
class TasksOverdue extends PreferenceNotification
{
    public function __construct(public int $assigned, public int $managed)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.tasks.overdue';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(__('Open tasks'), route('projects.tasks.index', ['preset' => 'overdue']));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => route('projects.tasks.index', ['preset' => 'overdue'])];
    }

    private function title(): string
    {
        return trans_choice(':count overdue task|:count overdue tasks', $this->assigned + $this->managed);
    }

    private function body(): string
    {
        return __('Yours: :assigned · On projects you manage: :managed', ['assigned' => $this->assigned, 'managed' => $this->managed]);
    }
}
