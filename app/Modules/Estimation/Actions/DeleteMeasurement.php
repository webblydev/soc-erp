<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a RECORDED or REJECTED MB entry (spec E14).
 */
class DeleteMeasurement
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, MeasurementEntry $entry): void
    {
        Gate::forUser($actor)->authorize('delete', $entry);

        if (! $entry->hasStatus(MbStatus::RECORDED) && ! $entry->hasStatus(MbStatus::REJECTED)) {
            throw ValidationException::withMessages(['entry' => __('A :status entry cannot be deleted.', ['status' => $entry->status->name])]);
        }

        DB::transaction(fn () => $entry->delete());
    }
}
