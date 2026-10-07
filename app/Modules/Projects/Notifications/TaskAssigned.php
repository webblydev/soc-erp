<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * projects.tasks.assigned (docs/04 §10): a task was assigned to the user's employee.
 */
class TaskAssigned extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Task $task)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.tasks.assigned';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Task :number assigned to you', ['number' => $this->task->task_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->task->title)
            ->lineIf($this->task->due_date !== null, __('Due :date.', ['date' => $this->task->due_date?->format('d-M-Y')]))
            ->action(__('Open task'), route('projects.tasks.show', $this->task));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Task :number assigned to you', ['number' => $this->task->task_number]),
            'body' => $this->task->title,
            'url' => route('projects.tasks.show', $this->task),
        ];
    }
}
