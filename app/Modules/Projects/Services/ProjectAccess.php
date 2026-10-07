<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Foundation\Models\Role;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\Task;
use App\Support\Facades\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Project and task visibility (docs/04 §2, spec P7, P8). "Own" goes through the user's linked
 * employee: project manager, supervisor, support officer or active team member.
 */
final class ProjectAccess
{
    /**
     * @param  Builder<covariant Project>  $query
     */
    public function scopeProjects(Builder $query, User $user): void
    {
        if ($this->seesAllProjects($user)) {
            return;
        }

        if (! $user->hasPermission('projects.projects.view_own')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $employeeId = $user->employee_id;

        $query->where(function (Builder $query) use ($user, $employeeId): void {
            if ($employeeId !== null) {
                $query->whereIn('projects.id', $this->memberProjectIds($employeeId));
            }

            $query->orWhere('projects.created_by', $user->id)
                ->orWhereIn('projects.customer_id', Customer::query()->select('id')
                    ->where(fn (Builder $query) => $query->where('account_manager_user_id', $user->id)->orWhere('acquired_by_user_id', $user->id)));
        });
    }

    /**
     * @param  Builder<covariant Task>  $query
     */
    public function scopeTasks(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission('projects.tasks.view_all')) {
            return;
        }

        $viaProjects = $user->hasPermission('projects.tasks.view_project');

        if (! $viaProjects && ! $user->hasPermission('projects.tasks.view_own')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($user, $viaProjects): void {
            $this->ownTasks($query, $user);

            if ($viaProjects) {
                $query->orWhereIn('tasks.project_id', Project::query()->visibleTo($user)->select('projects.id'));
            }
        });
    }

    /**
     * The user's own tasks: assignee, support officer or reviewer, assigner or watcher.
     *
     * @param  Builder<covariant Task>  $query
     */
    public function ownTasks(Builder $query, User $user): void
    {
        $employeeId = $user->employee_id;

        $query->where(function (Builder $query) use ($user, $employeeId): void {
            if ($employeeId !== null) {
                $query->where('tasks.assignee_employee_id', $employeeId)
                    ->orWhere('tasks.support_officer_id', $employeeId)
                    ->orWhere('tasks.reviewer_employee_id', $employeeId);
            }

            $query->orWhere('tasks.assigned_by', $user->id)
                ->orWhereIn('tasks.id', DB::table('task_watchers')->select('task_id')->where('user_id', $user->id));
        });
    }

    /**
     * view_all, super admin, or a project manager when projects.pm_can_view_all is on.
     */
    public function seesAllProjects(User $user): bool
    {
        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission('projects.projects.view_all')) {
            return true;
        }

        return $user->employee_id !== null
            && $user->hasPermission('projects.projects.view_own')
            && (bool) Settings::get('projects.pm_can_view_all', false)
            && Project::query()->where('project_manager_id', $user->employee_id)->exists();
    }

    /**
     * Whether the employee is the project's PM, supervisor, support officer or an active member.
     */
    public function isMember(Project $project, ?int $employeeId): bool
    {
        if ($employeeId === null) {
            return false;
        }

        return in_array($employeeId, [$project->project_manager_id, $project->supervisor_id, $project->support_officer_id], true)
            || $project->activeTeam()->where('employee_id', $employeeId)->exists();
    }

    public function isManager(Project $project, User $user): bool
    {
        return $user->employee_id !== null && $project->project_manager_id === $user->employee_id;
    }

    /**
     * Ids of projects the employee works on.
     *
     * @return Builder<Project>
     */
    private function memberProjectIds(int $employeeId): Builder
    {
        return Project::query()->withTrashed()->select('projects.id')
            ->where(fn (Builder $query) => $query->where('project_manager_id', $employeeId)
                ->orWhere('supervisor_id', $employeeId)
                ->orWhere('support_officer_id', $employeeId)
                ->orWhereIn('projects.id', ProjectEmployee::query()->select('project_id')->where('employee_id', $employeeId)->where('is_active', true)));
    }
}
