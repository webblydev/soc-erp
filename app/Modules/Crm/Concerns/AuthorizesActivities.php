<?php

namespace App\Modules\Crm\Concerns;

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

trait AuthorizesActivities
{
    /**
     * The actor needs the permission and must own the activity or be able to see its subject.
     *
     * @throws AuthorizationException
     */
    protected function authorizeActivity(User $actor, CrmActivity $activity, string $permission): void
    {
        Gate::forUser($actor)->authorize($permission);

        if ($activity->owner_user_id !== $actor->id) {
            $subject = $activity->subject;

            if ($subject === null) {
                throw new AuthorizationException;
            }

            Gate::forUser($actor)->authorize('view', $subject);
        }
    }
}
