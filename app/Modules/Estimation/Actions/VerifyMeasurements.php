<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Events\MeasurementVerified;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RECORDED → VERIFIED for one or more entries, all or none (docs/05 §6.2, spec E14). With
 * site.mb_requires_verification on, nobody verifies an entry they measured or recorded
 * (maker-checker, ES-BR-10, ES-AC-05).
 */
class VerifyMeasurements
{
    /**
     * @param  list<int>  $ids
     * @return int the number of entries verified
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $ids): int
    {
        $entries = MeasurementEntry::query()->visibleTo($actor)->with(['status', 'project'])->whereKey($ids)->get();
        $makerChecker = (bool) Settings::get('site.mb_requires_verification', true);
        $problems = [];

        if ($entries->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['entries' => __('Some entries could not be found.')]);
        }

        foreach ($entries as $entry) {
            Gate::forUser($actor)->authorize('verify', $entry);

            if (! $entry->hasStatus(MbStatus::RECORDED)) {
                $problems[] = __(':number is :status.', ['number' => $entry->mb_number, 'status' => $entry->status->name]);
            } elseif ($makerChecker && (($actor->employee_id !== null && $entry->measured_by === $actor->employee_id) || $entry->created_by === $actor->id)) {
                $problems[] = __(':number was measured or recorded by you; another person must verify it.', ['number' => $entry->mb_number]);
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['entries' => $problems]);
        }

        DB::transaction(function () use ($actor, $entries): void {
            foreach ($entries as $entry) {
                $entry->forceFill(['mb_status_id' => MbStatus::idFor(MbStatus::VERIFIED), 'verified_by' => $actor->id, 'verified_at' => now()])->save();
                MeasurementVerified::dispatch($entry);
            }
        });

        return $entries->count();
    }
}
