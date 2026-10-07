<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ProjectEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Releases a team member (docs/04 §3.6, spec P13).
 */
class ReleaseTeamMember
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectEmployee $member, array $input): void
    {
        Gate::forUser($actor)->authorize('manageTeam', $member->project);

        if (! $member->is_active) {
            throw ValidationException::withMessages(['released_on' => __('This member was already released.')]);
        }

        $data = Validator::make($input, [
            'released_on' => ['required', 'date', 'after_or_equal:'.$member->assigned_on->toDateString()],
        ], [], ['released_on' => __('release date')])->validate();

        DB::transaction(fn () => $member->forceFill(['released_on' => $data['released_on'], 'is_active' => false])->save());
    }
}
