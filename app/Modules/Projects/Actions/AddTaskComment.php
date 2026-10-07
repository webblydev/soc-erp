<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskComment;
use App\Modules\Projects\Notifications\TaskMentioned;
use App\Modules\Projects\Services\Mentions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds a comment to a task and notifies @mentioned users (spec P16).
 */
class AddTaskComment
{
    public function __construct(private Mentions $mentions) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Task $task, string $body): TaskComment
    {
        Gate::forUser($actor)->authorize('comment', $task);

        $data = Validator::make(['body' => trim($body)], ['body' => ['required', 'string', 'max:5000']], [], ['body' => __('comment')])->validate();

        $comment = DB::transaction(function () use ($actor, $task, $data): TaskComment {
            $comment = new TaskComment(['body' => $data['body']]);
            $comment->forceFill(['task_id' => $task->id, 'user_id' => $actor->id])->save();

            return $comment;
        });

        Notification::send($this->mentions->recipients($comment->body, $task, $actor), new TaskMentioned($comment));

        return $comment;
    }
}
