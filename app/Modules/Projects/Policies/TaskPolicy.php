<?php

namespace App\Modules\Projects\Policies;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Services\ProjectAccess;

/**
 * Single-task access (docs/04 §2, spec P8, PRJ-BR-12).
 */
class TaskPolicy
{
    public function __construct(private ProjectAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('projects.tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return Task::query()->whereKey($task->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('projects.tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('projects.tasks.update') && $this->view($user, $task)
            && ($this->isOverseer($user, $task) || $task->assigned_by === $user->id || $this->isWorker($user, $task));
    }

    /**
     * The assignee, support officer, reviewer, the project's PM or a view_all user (PRJ-BR-12).
     */
    public function changeStatus(User $user, Task $task): bool
    {
        return ($user->can('projects.tasks.update') || $user->can('projects.tasks.complete')) && $this->view($user, $task)
            && ($this->isOverseer($user, $task) || $this->isWorker($user, $task) || $this->isReviewer($user, $task));
    }

    /**
     * Completing or rejecting a task in review (spec P15).
     */
    public function review(User $user, Task $task): bool
    {
        return $this->changeStatus($user, $task) && ($this->isOverseer($user, $task) || $this->isReviewer($user, $task));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('projects.tasks.delete') && $this->view($user, $task)
            && ($this->isOverseer($user, $task) || $task->assigned_by === $user->id);
    }

    public function archive(User $user, Task $task): bool
    {
        return $user->can('projects.tasks.archive') && $this->view($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function logTime(User $user, Task $task): bool
    {
        return $user->employee_id !== null && $this->view($user, $task)
            && ($this->isWorker($user, $task) || $this->isReviewer($user, $task) || $this->isOverseer($user, $task));
    }

    /**
     * A view_all user with update, or the PM of the task's project.
     */
    private function isOverseer(User $user, Task $task): bool
    {
        if ($user->can('projects.tasks.view_all') && $user->can('projects.tasks.update')) {
            return true;
        }

        return $task->project !== null && $this->access->isManager($task->project, $user);
    }

    private function isWorker(User $user, Task $task): bool
    {
        return $user->employee_id !== null && in_array($user->employee_id, [$task->assignee_employee_id, $task->support_officer_id], true);
    }

    private function isReviewer(User $user, Task $task): bool
    {
        return $user->employee_id !== null && $task->reviewer_employee_id === $user->employee_id;
    }
}
