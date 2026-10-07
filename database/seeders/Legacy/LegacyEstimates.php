<?php

namespace Database\Seeders\Legacy;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

/**
 * Shared parts of the v1 work and material estimate importers (legacy seed spec L17, L18): the
 * APPROVED estimate header and unit matching.
 */
class LegacyEstimates
{
    /** @var Collection<int, Unit>|null */
    private ?Collection $units = null;

    public function __construct(private NumberSequenceService $numbers) {}

    /**
     * Create the header of an imported estimate; null when its v1 project was not imported.
     */
    public function header(LegacyContext $context, stdClass $row, string $kindCode, string $titlePrefix, string $legacyRef): ?Estimate
    {
        $project = isset($context->projects[(int) $row->project_id]) ? Project::withTrashed()->find($context->projects[(int) $row->project_id]) : null;

        if ($project === null) {
            return null;
        }

        $addedAt = LegacyMap::dateTime($row->AddTime) ?? Carbon::parse((LegacyMap::date($row->date) ?? today()->toDateString()).' 10:00:00');
        $date = LegacyMap::date($row->date) ?? $addedAt->toDateString();
        $preparer = $project->project_manager_id ?? (int) Employee::query()->assignable()->orderBy('id')->value('id');
        $approved = $context->idFor(EstimateStatus::class, EstimateStatus::APPROVED);

        $estimate = new Estimate;
        $estimate->forceFill([
            'estimate_number' => $this->numbers->next('estimate', ['date' => $date]),
            'estimate_kind_id' => $context->idFor(EstimateKind::class, $kindCode),
            'project_id' => $project->id,
            'title' => Str::limit($titlePrefix.' — '.$project->name, 255, ''),
            'site_address' => trim((string) $row->address) !== '' ? trim((string) $row->address) : $project->site_address,
            'estimate_date' => $date,
            'revision_no' => 0,
            'estimate_status_id' => $approved,
            'prepared_by' => $preparer,
            'approved_at' => $addedAt,
            'is_customer_facing' => false,
            'legacy_ref' => $legacyRef,
            'created_by' => $project->created_by ?? $context->fallbackUserId(),
            'updated_by' => $project->created_by ?? $context->fallbackUserId(),
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ])->save();

        $estimate->statusHistories()->create([
            'estimate_status_id' => $approved, 'note' => __('Imported from v1.'), 'changed_at' => $addedAt,
        ]);

        return $estimate;
    }

    /**
     * A unit whose code, symbol or name equals the v1 text, ignoring case.
     */
    public function unitFor(?string $text): ?int
    {
        $key = Str::lower(trim((string) $text));

        if ($key === '') {
            return null;
        }

        $this->units ??= Unit::query()->get(['id', 'code', 'symbol', 'name']);

        return $this->units->first(fn (Unit $unit): bool => in_array($key, [Str::lower($unit->code), Str::lower((string) $unit->symbol), Str::lower($unit->name)], true))?->id;
    }

    public function defaultUnit(): int
    {
        return (int) ($this->unitFor('nos') ?? Unit::query()->orderBy('id')->value('id'));
    }
}
