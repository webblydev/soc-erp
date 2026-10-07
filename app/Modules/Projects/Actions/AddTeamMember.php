<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Notifications\TeamMemberAdded;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds an employee to a project team (docs/04 §3.6, spec P13). One active row per employee and
 * role; the key people of the project are added through ensureMember() (spec P6).
 */
class AddTeamMember
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): ProjectEmployee
    {
        Gate::forUser($actor)->authorize('manageTeam', $project);

        $data = Validator::make($input, [
            'employee_id' => ['required', new AssignableEmployee],
            'project_role_id' => ['required', new ActiveLookup('project_roles')],
            'allocation_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assigned_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_id' => __('employee'), 'project_role_id' => __('role')])->validate();

        if ($this->activeRow($project, (int) $data['employee_id'], (int) $data['project_role_id']) !== null) {
            throw ValidationException::withMessages(['employee_id' => __('This employee already has that role on the project.')]);
        }

        return DB::transaction(function () use ($actor, $project, $data): ProjectEmployee {
            $member = $project->team()->create([
                'employee_id' => (int) $data['employee_id'],
                'project_role_id' => (int) $data['project_role_id'],
                'allocation_pct' => $data['allocation_pct'] ?? null,
                'assigned_on' => $data['assigned_on'],
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);

            $this->notify($actor, $member);

            return $member;
        });
    }

    /**
     * Make sure the employee holds the role on the project (no authorization; caller's transaction).
     */
    public function ensureMember(User $actor, Project $project, ?int $employeeId, string $roleCode, bool $notify = true): void
    {
        if ($employeeId === null) {
            return;
        }

        $roleId = ProjectRole::idFor($roleCode);

        if ($this->activeRow($project, $employeeId, $roleId) !== null) {
            return;
        }

        $member = $project->team()->create([
            'employee_id' => $employeeId,
            'project_role_id' => $roleId,
            'assigned_on' => today()->toDateString(),
            'is_active' => true,
        ]);

        if ($notify) {
            $this->notify($actor, $member);
        }
    }

    private function activeRow(Project $project, int $employeeId, int $roleId): ?ProjectEmployee
    {
        return $project->activeTeam()->where('employee_id', $employeeId)->where('project_role_id', $roleId)->first();
    }

    private function notify(User $actor, ProjectEmployee $member): void
    {
        $user = Employee::query()->find($member->employee_id)?->user;

        if ($user !== null && $user->is_active && $user->id !== $actor->id) {
            $user->notify(new TeamMemberAdded($member));
        }
    }
}
