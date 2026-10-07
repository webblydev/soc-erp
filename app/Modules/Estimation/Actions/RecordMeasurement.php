<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ValidatesMeasurementInput;
use App\Modules\Estimation\Events\MeasurementVerified;
use App\Modules\Estimation\Models\MbDirection;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Projects\Models\Project;
use App\Support\Facades\Settings;
use App\Support\NumberSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records a customer-direction MB entry (docs/05 §3.7, spec E13). With site.mb_requires_verification
 * off the entry is saved VERIFIED by its creator (spec E14). Read $warnings after handle().
 */
class RecordMeasurement
{
    use ValidatesMeasurementInput;

    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $input): MeasurementEntry
    {
        Gate::forUser($actor)->authorize('recordMeasurement', $project);

        $attributes = $this->validateMeasurement($actor, $project, $input, null);
        $verified = ! (bool) Settings::get('site.mb_requires_verification', true);

        return DB::transaction(function () use ($actor, $project, $attributes, $verified): MeasurementEntry {
            $entry = new MeasurementEntry($attributes);
            $entry->forceFill([
                'mb_number' => $this->numbers->next('mb_entry', ['date' => $attributes['measured_on']]),
                'project_id' => $project->id,
                'mb_direction_id' => MbDirection::idFor(MbDirection::CUSTOMER),
                'mb_status_id' => MbStatus::idFor($verified ? MbStatus::VERIFIED : MbStatus::RECORDED),
                'verified_by' => $verified ? $actor->id : null,
                'verified_at' => $verified ? now() : null,
            ])->save();

            if ($verified) {
                MeasurementVerified::dispatch($entry);
            }

            return $entry;
        });
    }
}
