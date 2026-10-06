<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Notifications\LeadAssigned;
use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assigns a lead to a salesperson (CRM-BR-05, CRM-BR-06, spec R8). Self-assignment is always
 * allowed; otherwise crm.leads.assign with view_all reaches anyone and with view_team reaches
 * the active members of the teams the actor manages.
 */
class AssignLead
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, ?int $userId, ?string $reason = null): Lead
    {
        Gate::forUser($actor)->authorize('assign', $lead);

        if ($lead->isConverted() || ! $lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => __('Only open leads can be reassigned.')]);
        }

        if ($userId === $lead->assigned_to) {
            return $lead;
        }

        DB::transaction(fn () => $this->assign($actor, $lead, $userId, $reason));

        return $lead->refresh();
    }

    /**
     * Writes the assignment, its history row and the notification. Callers authorize and
     * run it inside their transaction. A system pick (round-robin, spec R9) skips the actor's
     * assignment scope; the assignee must still be an active user who can see leads.
     *
     * @throws ValidationException
     */
    public function assign(User $actor, Lead $lead, ?int $userId, ?string $reason, bool $systemPick = false): void
    {
        if ($userId !== null && ! ($systemPick ? $this->isAssignable($userId) : $this->canAssignTo($actor, $userId))) {
            throw ValidationException::withMessages(['assigned_to' => __('You cannot assign leads to this person.')]);
        }

        $previous = $lead->assigned_to;

        $lead->forceFill([
            'assigned_to' => $userId,
            'assigned_at' => $userId === null ? null : now(),
            'sales_team_id' => $userId === null ? $lead->sales_team_id : (SalesTeam::activeTeamIdFor($userId) ?? $lead->sales_team_id),
        ])->save();

        $lead->assignmentHistories()->create([
            'from_user_id' => $previous,
            'to_user_id' => $userId,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'reason' => $reason,
        ]);

        if ($userId !== null && $userId !== $actor->id) {
            User::query()->findOrFail($userId)->notify(new LeadAssigned($lead));
        }
    }

    /**
     * An active user who can see leads at all (spec R8's "assignable users").
     */
    public function isAssignable(?int $userId): bool
    {
        $target = $userId === null ? null : User::query()->where('is_active', true)->find($userId);

        return $target !== null && $target->can('crm.leads.view');
    }

    public function canAssignTo(User $actor, int $userId): bool
    {
        if (! $this->isAssignable($userId)) {
            return false;
        }

        if ($userId === $actor->id) {
            return true;
        }

        if (! $actor->can('crm.leads.assign')) {
            return false;
        }

        if ($actor->hasRole(Role::SUPER_ADMIN) || $actor->hasPermission('crm.leads.view_all')) {
            return true;
        }

        return $actor->hasPermission('crm.leads.view_team') && in_array($userId, SalesTeam::managedMemberIds($actor), true);
    }

    /**
     * People the actor may assign to, for pickers.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(User $actor): Collection
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->filter(fn (User $user): bool => $this->canAssignTo($actor, $user->id))
            ->values();
    }
}
