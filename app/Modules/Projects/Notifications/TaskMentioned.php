<?php

namespace App\Modules\Projects\Notifications;

use App\Models\User;
use App\Modules\Projects\Models\TaskComment;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * projects.tasks.mentioned (docs/04 §10): the user was @mentioned in a task comment.
 */
class TaskMentioned extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public TaskComment $comment)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'projects.tasks.mentioned';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __(':name mentioned you on :number', ['name' => $this->comment->user->name, 'number' => $this->comment->task->task_number]),
            'body' => Str::limit($this->comment->body, 140),
            'url' => route('projects.tasks.show', $this->comment->task),
        ];
    }
}
