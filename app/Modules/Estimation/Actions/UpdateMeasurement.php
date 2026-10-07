<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ValidatesMeasurementInput;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a RECORDED or REJECTED MB entry; a rejected entry goes back to RECORDED (spec E13).
 */
class UpdateMeasurement
{
    use ValidatesMeasurementInput;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, MeasurementEntry $entry, array $input): MeasurementEntry
    {
        Gate::forUser($actor)->authorize('update', $entry);

        if (! $entry->hasStatus(MbStatus::RECORDED) && ! $entry->hasStatus(MbStatus::REJECTED)) {
            throw ValidationException::withMessages(['entry' => __('A :status entry cannot be edited.', ['status' => $entry->status->name])]);
        }

        $attributes = $this->validateMeasurement($actor, $entry->project, $input, $entry);

        return DB::transaction(function () use ($entry, $attributes): MeasurementEntry {
            $entry->fill($attributes);

            if ($entry->hasStatus(MbStatus::REJECTED)) {
                $entry->forceFill(['mb_status_id' => MbStatus::idFor(MbStatus::RECORDED), 'rejection_reason' => null]);
                $entry->unsetRelation('status');
            }

            $entry->save();

            return $entry;
        });
    }
}
