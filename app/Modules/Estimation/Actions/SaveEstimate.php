<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Concerns\ChangesEstimateStatus;
use App\Modules\Estimation\Concerns\ValidatesEstimateInput;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Services\EstimateTotals;
use App\Modules\Projects\Models\Project;
use App\Support\NumberSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates an estimate or saves a draft: header, sections, work lines and material lines together
 * (docs/05 §5.2, spec E5–E8). Locked estimates change only through a revision (ES-BR-01); saving a
 * rejected estimate moves it back to DRAFT.
 *
 * @phpstan-import-type Section from ValidatesEstimateInput
 * @phpstan-import-type MaterialLine from ValidatesEstimateInput
 */
class SaveEstimate
{
    use ChangesEstimateStatus, ValidatesEstimateInput;

    public function __construct(private NumberSequenceService $numbers, private EstimateTotals $totals) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?Estimate $estimate = null, ?Project $project = null): Estimate
    {
        if ($estimate === null) {
            if ($project === null) {
                throw ValidationException::withMessages(['project_id' => __('Choose the project.')]);
            }

            Gate::forUser($actor)->authorize('createEstimate', $project);

            $input['prepared_by'] ??= $actor->employee_id;
            $input['site_address'] ??= $project->site_address;
        } else {
            Gate::forUser($actor)->authorize('update', $estimate);

            if (! $estimate->hasStatus(EstimateStatus::DRAFT) && ! $estimate->hasStatus(EstimateStatus::REJECTED)) {
                throw ValidationException::withMessages(['estimate' => __('This estimate is :status and cannot be edited; revise it instead.', ['status' => $estimate->status->name])]);
            }

            $input['prepared_by'] ??= $estimate->prepared_by;
        }

        $data = $this->validateEstimate($input, $estimate);

        return DB::transaction(function () use ($actor, $estimate, $project, $data): Estimate {
            $header = $data['header'];
            unset($header['estimate_kind_id']);

            if ($estimate === null) {
                /** @var Project $project */
                $kind = EstimateKind::query()->findOrFail((int) $data['header']['estimate_kind_id']);
                $estimate = new Estimate($header);
                $estimate->forceFill([
                    'estimate_number' => $this->numbers->next('estimate', ['date' => $header['estimate_date']]),
                    'estimate_kind_id' => $kind->id,
                    'project_id' => $project->id,
                    'revision_no' => 0,
                    'estimate_status_id' => EstimateStatus::idFor(EstimateStatus::DRAFT),
                    'is_customer_facing' => $kind->is_customer_facing,
                ])->save();

                $estimate->statusHistories()->create([
                    'estimate_status_id' => $estimate->estimate_status_id, 'changed_by' => $actor->id, 'changed_at' => now(),
                ]);
            } else {
                $estimate->fill($header)->save();

                if ($estimate->hasStatus(EstimateStatus::REJECTED)) {
                    $this->moveTo($estimate, EstimateStatus::DRAFT, $actor, __('Edited after rejection.'));
                }
            }

            $this->saveSections($estimate, $data['sections']);
            $this->saveMaterialLines($estimate, $data['material_lines']);
            $this->totals->refresh($estimate);

            return $estimate->refresh();
        });
    }

    /**
     * Sections and their work lines in an input shape SaveEstimate accepts, in the saved order: for
     * the editor (with ids) and for "copy lines from" (without ids or origins, so the copies are new
     * lines, spec E8).
     *
     * @return array{sections: list<array<string, mixed>>, material_lines: list<array<string, mixed>>}
     */
    public static function linesOf(Estimate $source, bool $withIds = false): array
    {
        $source->loadMissing(['sections', 'lines', 'materialLines']);
        $line = fn ($line): array => [
            ...($withIds ? ['id' => $line->id] : []),
            'line_no' => $line->line_no, 'work_item_id' => $line->work_item_id, 'description' => $line->description,
            'level' => $line->level, 'location' => $line->location, 'measurement_formula' => $line->measurement_formula->value,
            'nos' => $line->nos, 'length' => $line->length, 'width' => $line->width, 'height' => $line->height,
            'deduction' => $line->deduction, 'unit_id' => $line->unit_id, 'quantity' => $line->quantity_is_manual ? $line->quantity : null,
            'rate' => $line->rate, 'cost_category_id' => $line->cost_category_id, 'remarks' => $line->remarks,
        ];

        $groups = $source->lines->groupBy(fn ($line): string => (string) $line->estimate_section_id);
        $order = $groups->map(fn ($lines): int => (int) $lines->min('sort_order'))->sort();
        $sectionsById = $source->sections->keyBy('id');
        $sections = [];

        foreach ($order->keys() as $sectionId) {
            $section = $sectionsById->get((int) $sectionId);
            $sections[] = [
                ...($withIds ? ['id' => $section?->id] : []),
                'name' => $section?->name,
                'lines' => $groups[$sectionId]->map($line)->values()->all(),
            ];
        }

        foreach ($source->sections->whereNotIn('id', $groups->keys()->map(fn ($id): int => (int) $id)) as $empty) {
            $sections[] = [...($withIds ? ['id' => $empty->id] : []), 'name' => $empty->name, 'lines' => []];
        }

        return [
            'sections' => $sections,
            'material_lines' => $source->materialLines->map(fn ($material): array => [
                ...($withIds ? ['id' => $material->id] : []),
                'material_id' => $material->material_id, 'material_name' => $material->material_name, 'unit_id' => $material->unit_id,
                'estimated_qty' => $material->estimated_qty, 'wastage_pct' => $material->wastage_pct, 'rate' => $material->rate,
                'purpose' => $material->purpose,
            ])->values()->all(),
        ];
    }

    /**
     * Replace the sections and work lines, keeping posted ids. A blank section name stores its lines
     * without a section.
     *
     * @param  list<Section>  $sections
     */
    private function saveSections(Estimate $estimate, array $sections): void
    {
        $keepSections = array_values(array_filter(array_map(fn (array $section): ?int => $section['name'] === null ? null : $section['id'], $sections)));
        $keepLines = array_values(array_filter(array_merge(...array_map(fn (array $section): array => array_column($section['lines'], 'id'), $sections ?: [['lines' => []]]))));

        $estimate->lines()->whereNotIn('id', $keepLines)->get()->each->delete();
        $estimate->sections()->whereNotIn('id', $keepSections)->delete();

        $order = 0;

        foreach ($sections as $position => $section) {
            $sectionId = null;

            if ($section['name'] !== null) {
                $model = $section['id'] !== null && in_array($section['id'], $keepSections, true)
                    ? $estimate->sections()->findOrFail($section['id'])
                    : $estimate->sections()->make();
                $model->fill(['name' => $section['name'], 'sort_order' => $position + 1])->save();
                $sectionId = $model->id;
            }

            foreach ($section['lines'] as $line) {
                $attributes = [...$line, 'estimate_section_id' => $sectionId, 'sort_order' => ++$order];
                unset($attributes['id']);

                $line['id'] !== null
                    ? $estimate->lines()->findOrFail($line['id'])->fill($attributes)->save()
                    : $estimate->lines()->create($attributes);
            }
        }
    }

    /**
     * @param  list<MaterialLine>  $lines
     */
    private function saveMaterialLines(Estimate $estimate, array $lines): void
    {
        $estimate->materialLines()->whereNotIn('id', array_values(array_filter(array_column($lines, 'id'))))->get()->each->delete();

        foreach ($lines as $index => $line) {
            $attributes = [...$line, 'sort_order' => $index + 1];
            unset($attributes['id']);

            $line['id'] !== null
                ? $estimate->materialLines()->findOrFail($line['id'])->fill($attributes)->save()
                : $estimate->materialLines()->create($attributes);
        }
    }
}
