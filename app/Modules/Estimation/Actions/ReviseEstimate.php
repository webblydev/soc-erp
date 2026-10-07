<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Services\EstimateTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Starts the next revision of an approved estimate (docs/05 §6.1, ES-AC-02, spec E10): a DRAFT copy
 * of the header, sections and lines numbered "{root}-R{n}" (n counts deleted drafts, so a number is
 * never reused). Lines remember the line they were
 * copied from through origin_line_id. The approved revision stays APPROVED until the new one is.
 */
class ReviseEstimate
{
    public function __construct(private EstimateTotals $totals) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Estimate $estimate, ?string $purpose): Estimate
    {
        Gate::forUser($actor)->authorize('revise', $estimate);

        if (! $estimate->hasStatus(EstimateStatus::APPROVED) || ! $estimate->isLatestRevision()) {
            throw ValidationException::withMessages(['estimate' => __('Only the latest approved revision can be revised.')]);
        }

        $open = EstimateStatus::query()->whereIn('code', [EstimateStatus::DRAFT, EstimateStatus::SUBMITTED, EstimateStatus::REJECTED])->pluck('id');

        if (Estimate::query()->familyOf($estimate)->whereIn('estimate_status_id', $open)->exists()) {
            throw ValidationException::withMessages(['estimate' => __('This estimate already has an open revision.')]);
        }

        $purpose = Validator::make(['purpose' => is_string($purpose) ? trim($purpose) : $purpose], ['purpose' => ['required', 'string', 'max:500']], [], ['purpose' => __('purpose of the revision')])->validate()['purpose'];

        return DB::transaction(function () use ($actor, $estimate, $purpose): Estimate {
            $estimate->refresh()->load(['sections', 'lines', 'materialLines', 'root']);
            $revisionNo = (int) Estimate::withTrashed()->familyOf($estimate)->max('revision_no') + 1;
            $rootNumber = ($estimate->root ?? $estimate)->estimate_number;

            $revision = new Estimate([
                'title' => $estimate->title, 'site_address' => $estimate->site_address, 'estimate_date' => today(),
                'prepared_by' => $actor->employee_id ?? $estimate->prepared_by, 'overhead_pct' => $estimate->overhead_pct,
                'profit_pct' => $estimate->profit_pct, 'vat_pct' => $estimate->vat_pct, 'notes' => $estimate->notes,
            ]);
            $revision->forceFill([
                'estimate_number' => $rootNumber.'-R'.$revisionNo,
                'estimate_kind_id' => $estimate->estimate_kind_id,
                'project_id' => $estimate->project_id,
                'revision_no' => $revisionNo,
                'root_estimate_id' => $estimate->rootId(),
                'revised_from_id' => $estimate->id,
                'revision_purpose' => $purpose,
                'estimate_status_id' => EstimateStatus::idFor(EstimateStatus::DRAFT),
                'is_customer_facing' => (bool) $estimate->is_customer_facing,
            ])->save();

            $revision->statusHistories()->create([
                'estimate_status_id' => $revision->estimate_status_id, 'note' => $purpose, 'changed_by' => $actor->id, 'changed_at' => now(),
            ]);

            $sectionIds = [];

            foreach ($estimate->sections as $section) {
                $sectionIds[$section->id] = $revision->sections()->create($section->only(['name', 'sort_order']))->id;
            }

            foreach ($estimate->lines as $line) {
                $copy = $line->replicate(['estimate_id', 'estimate_section_id', 'origin_line_id']);
                $copy->forceFill([
                    'estimate_id' => $revision->id,
                    'estimate_section_id' => $line->estimate_section_id !== null ? $sectionIds[$line->estimate_section_id] : null,
                    'origin_line_id' => $line->originId(),
                ])->save();
            }

            foreach ($estimate->materialLines as $line) {
                $copy = $line->replicate(['estimate_id', 'origin_line_id']);
                $copy->forceFill(['estimate_id' => $revision->id, 'origin_line_id' => $line->originId()])->save();
            }

            $this->totals->refresh($revision);

            return $revision->refresh();
        });
    }
}
