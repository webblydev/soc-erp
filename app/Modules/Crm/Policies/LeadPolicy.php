<?php

namespace App\Modules\Crm\Policies;

use App\Models\User;
use App\Modules\Crm\Models\Lead;

/**
 * Single-lead access (docs/03 §2, spec R6). Super admins pass through Gate::before, so Actions
 * also check the lead's state (converted, closed) themselves.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return Lead::query()->whereKey($lead->id)->visibleTo($user, 'crm.leads')->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('crm.leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.update') && ! $lead->isConverted() && $this->view($user, $lead);
    }

    public function changeStatus(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.assign') && ! $lead->isConverted() && $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.delete') && ! $lead->isConverted() && $this->view($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.convert') && ! $lead->isConverted() && $lead->isOpen() && $this->view($user, $lead);
    }
}
