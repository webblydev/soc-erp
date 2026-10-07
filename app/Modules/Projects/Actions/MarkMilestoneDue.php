<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Services\ScheduleTriggers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marks a pending milestone due by hand (spec P12), e.g. a MANUAL trigger.
 */
class MarkMilestoneDue
{
    public function __construct(private ScheduleTriggers $triggers) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, PaymentSchedule $schedule): void
    {
        Gate::forUser($actor)->authorize('manageContract', $schedule->project);

        if (! $schedule->isPending()) {
            throw ValidationException::withMessages(['schedule' => __('Only a pending milestone can be marked due.')]);
        }

        DB::transaction(fn () => $this->triggers->markDue($schedule));
    }
}
