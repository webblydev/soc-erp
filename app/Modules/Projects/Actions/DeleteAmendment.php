<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ProjectContractAmendment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a draft amendment (spec P11).
 */
class DeleteAmendment
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectContractAmendment $amendment): void
    {
        Gate::forUser($actor)->authorize('manageContract', $amendment->contract->project);

        if (! $amendment->isDraft()) {
            throw ValidationException::withMessages(['amendment' => __('Approved amendments cannot be deleted.')]);
        }

        DB::transaction(fn () => $amendment->delete());
    }
}
