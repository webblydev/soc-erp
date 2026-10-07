<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\ProjectService;
use App\Modules\Projects\Models\ProjectServiceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records delivery progress of a service line (spec P5): NOT_STARTED → IN_PROGRESS → DELIVERED.
 * Cancelling a line changes the contract value, so it goes through the services grid or, once
 * signed, an amendment.
 */
class SetServiceStatus
{
    private const ALLOWED = [ProjectServiceStatus::NOT_STARTED, ProjectServiceStatus::IN_PROGRESS, ProjectServiceStatus::DELIVERED];

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, ProjectService $line, string $code, ?string $deliveredOn = null): void
    {
        Gate::forUser($actor)->authorize('update', $line->project);

        if (! in_array($code, self::ALLOWED, true) || $line->isCancelled()) {
            throw ValidationException::withMessages(['status' => __('This delivery status cannot be set here.')]);
        }

        $date = $code === ProjectServiceStatus::DELIVERED ? ($deliveredOn ?: today()->toDateString()) : null;

        if ($date !== null && (strtotime($date) === false || $date > today()->toDateString())) {
            throw ValidationException::withMessages(['delivered_on' => __('The delivery date cannot be in the future.')]);
        }

        DB::transaction(fn () => $line->forceFill([
            'project_service_status_id' => ProjectServiceStatus::idFor($code),
            'delivered_on' => $date,
        ])->save());
    }
}
