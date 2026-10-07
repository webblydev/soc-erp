<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Foundation\Models\DocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Moves a finding on (docs/05 §6.3, spec E16): OPEN → IN_PROGRESS → RESOLVED / ACCEPTED, or OPEN
 * straight to a closed status. Closing needs a note and may carry an "after" photo; the PM or
 * management reopens a closed finding. When the last open finding of a submitted inspection
 * closes, the inspection closes too.
 */
class ChangeFindingStatus
{
    /**
     * Allowed moves by status code.
     */
    private const MOVES = [
        FindingStatus::OPEN => [FindingStatus::IN_PROGRESS, FindingStatus::RESOLVED, FindingStatus::ACCEPTED],
        FindingStatus::IN_PROGRESS => [FindingStatus::OPEN, FindingStatus::RESOLVED, FindingStatus::ACCEPTED],
        FindingStatus::RESOLVED => [FindingStatus::OPEN],
        FindingStatus::ACCEPTED => [FindingStatus::OPEN],
    ];

    public function __construct(private UploadAttachment $uploadAttachment) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, SiteInspectionFinding $finding, string $toCode, ?string $note = null, ?UploadedFile $photo = null): SiteInspectionFinding
    {
        $from = $finding->status;
        $to = FindingStatus::query()->where('code', $toCode)->first();

        Gate::forUser($actor)->authorize($from->is_closed ? 'reopen' : 'changeStatus', $finding);

        if ($finding->inspection->hasStatus(InspectionStatus::DRAFT)) {
            throw ValidationException::withMessages(['status' => __('Submit the inspection before following up its findings.')]);
        }

        if ($to === null || ! in_array($to->code, self::MOVES[$from->code] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('A finding cannot move from :from to :to.', ['from' => $from->name, 'to' => $to->name ?? $toCode])]);
        }

        $note = is_string($note) && trim($note) !== '' ? trim($note) : null;

        if ($to->is_closed) {
            Validator::make(['note' => $note], ['note' => ['required', 'string', 'max:5000']], [], ['note' => __('closure note')])->validate();
        }

        DB::transaction(function () use ($actor, $finding, $to, $note, $photo): void {
            $finding->forceFill([
                'finding_status_id' => $to->id,
                'closed_on' => $to->is_closed ? today() : null,
                'closed_by' => $to->is_closed ? $actor->id : null,
                'closure_note' => $to->is_closed ? $note : null,
                'overdue_notified_at' => null,
            ])->save();
            $finding->unsetRelation('status');

            if ($photo !== null) {
                $this->uploadAttachment->handle($finding, $photo, $actor, [
                    'document_type_id' => DocumentType::query()->where('code', 'site_photo')->value('id'),
                    'title' => __('After: :location', ['location' => $finding->location ?? __('finding')]),
                ]);
            }

            $inspection = $finding->inspection;

            if ($to->is_closed && $inspection->hasStatus(InspectionStatus::SUBMITTED) && ! $inspection->findings()->open()->exists()) {
                $inspection->forceFill(['inspection_status_id' => InspectionStatus::idFor(InspectionStatus::CLOSED)])->save();
            }
        });

        return $finding;
    }
}
