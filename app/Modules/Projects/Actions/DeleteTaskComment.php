<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\TaskComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a comment: its author or a user with projects.tasks.delete (spec P16).
 */
class DeleteTaskComment
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, TaskComment $comment): void
    {
        if ($comment->user_id !== $actor->id && ! ($actor->can('projects.tasks.delete') && $actor->can('view', $comment->task))) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => $comment->delete());
    }
}
