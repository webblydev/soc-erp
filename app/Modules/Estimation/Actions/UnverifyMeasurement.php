<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * VERIFIED → RECORDED while the entry is on no running bill (ES-BR-09, spec E14).
 */
class UnverifyMeasurement
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, MeasurementEntry $entry): MeasurementEntry
    {
        Gate::forUser($actor)->authorize('verify', $entry);

        if (! $entry->hasStatus(MbStatus::VERIFIED) || $entry->running_bill_line_id !== null) {
            throw ValidationException::withMessages(['entry' => __('Only a verified entry that is not billed can be unverified.')]);
        }

        DB::transaction(fn () => $entry->forceFill(['mb_status_id' => MbStatus::idFor(MbStatus::RECORDED), 'verified_by' => null, 'verified_at' => null])->save());
        $entry->unsetRelation('status');

        return $entry;
    }
}
