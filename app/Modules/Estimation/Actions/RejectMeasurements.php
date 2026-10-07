<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RECORDED → REJECTED with a reason, all or none (docs/05 §6.2, spec E14). The measurer edits a
 * rejected entry back to RECORDED.
 */
class RejectMeasurements
{
    /**
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $ids, ?string $reason): int
    {
        $reason = Validator::make(['reason' => is_string($reason) ? trim($reason) : $reason], ['reason' => ['required', 'string', 'max:255']])->validate()['reason'];
        $entries = MeasurementEntry::query()->visibleTo($actor)->with(['status', 'project'])->whereKey($ids)->get();

        if ($entries->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['entries' => __('Some entries could not be found.')]);
        }

        foreach ($entries as $entry) {
            Gate::forUser($actor)->authorize('verify', $entry);

            if (! $entry->hasStatus(MbStatus::RECORDED)) {
                throw ValidationException::withMessages(['entries' => __(':number is :status.', ['number' => $entry->mb_number, 'status' => $entry->status->name])]);
            }
        }

        DB::transaction(function () use ($entries, $reason): void {
            foreach ($entries as $entry) {
                $entry->forceFill(['mb_status_id' => MbStatus::idFor(MbStatus::REJECTED), 'rejection_reason' => $reason])->save();
            }
        });

        return $entries->count();
    }
}
