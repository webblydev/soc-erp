<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ValidatesInspectionInput;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Projects\Models\Project;
use App\Support\NumberSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates an inspection or saves it (docs/05 §5.8, spec E15). While DRAFT the findings are saved
 * with it as a repeater; once SUBMITTED only the header changes and findings come through
 * AddFinding. CLOSED inspections are read-only.
 */
class SaveInspection
{
    use ValidatesInspectionInput;

    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * @param  array<string, mixed>  $input  header fields and `findings` (DRAFT only)
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?SiteInspection $inspection = null, ?Project $project = null): SiteInspection
    {
        if ($inspection === null) {
            if ($project === null) {
                throw ValidationException::withMessages(['project_id' => __('Choose the project.')]);
            }

            Gate::forUser($actor)->authorize('createInspection', $project);
            $input['project_engineer_id'] ??= $actor->employee_id;
            $input['site_address'] ??= $project->site_address;
        } else {
            Gate::forUser($actor)->authorize('update', $inspection);
            $project = $inspection->project;

            if ($inspection->hasStatus(InspectionStatus::CLOSED)) {
                throw ValidationException::withMessages(['inspection' => __('A closed inspection cannot be edited.')]);
            }
        }

        $isDraft = $inspection === null || $inspection->hasStatus(InspectionStatus::DRAFT);
        $header = $this->validateInspection($project, $input, $inspection);
        $findings = $isDraft
            ? $this->validateFindings($project, is_array($input['findings'] ?? null) ? $input['findings'] : [], $inspection?->findings()->pluck('id')->all() ?? [])
            : null;

        return DB::transaction(function () use ($project, $inspection, $header, $findings): SiteInspection {
            if ($inspection === null) {
                $inspection = new SiteInspection($header);
                $inspection->forceFill([
                    'inspection_number' => $this->numbers->next('site_inspection', ['date' => $header['inspection_date']]),
                    'project_id' => $project->id,
                    'inspection_status_id' => InspectionStatus::idFor(InspectionStatus::DRAFT),
                ])->save();
            } else {
                $inspection->fill($header)->save();
            }

            if ($findings !== null) {
                $inspection->findings()->whereNotIn('id', array_values(array_filter(array_column($findings, 'id'))))->get()->each->delete();
                $open = FindingStatus::idFor(FindingStatus::OPEN);

                foreach ($findings as $index => $row) {
                    $attributes = [...$row, 'sort_order' => $index + 1];
                    unset($attributes['id']);

                    $finding = $row['id'] !== null ? $inspection->findings()->findOrFail($row['id']) : $inspection->findings()->make();
                    $finding->fill($attributes);

                    if (! $finding->exists) {
                        $finding->forceFill(['project_id' => $project->id, 'finding_status_id' => $open]);
                    }

                    $finding->save();
                }
            }

            return $inspection->refresh();
        });
    }
}
