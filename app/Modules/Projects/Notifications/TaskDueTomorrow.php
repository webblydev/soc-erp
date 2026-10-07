<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * projects.tasks.due_tomorrow (docs/04 §10): daily, to the assignee.
 */
class TaskDueTomorrow extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Task $task)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.tasks.due_tomorrow';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Task :number is due tomorrow', ['number' => $this->task->task_number]),
            'body' => $this->task->title,
            'url' => route('projects.tasks.show', $this->task),
        ];
    }
}
