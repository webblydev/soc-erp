<?php

namespace App\Modules\Estimation\Concerns;

use App\Models\User;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Services\MeasurementLimits;
use App\Modules\Estimation\Services\QuantityCalculator;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use App\Support\Lookups\ActiveLookup;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * MB entry rules shared by RecordMeasurement and UpdateMeasurement (docs/05 §5.6, ES-BR-07, 08, 13,
 * spec E13). A BOQ line fills the item, description, unit, formula and rate; the rate may differ
 * from its default only with site.mb.edit_rate.
 */
trait ValidatesMeasurementInput
{
    /**
     * Soft warnings for the UI, such as a quantity above the BOQ within the allowed margin.
     *
     * @var list<string>
     */
    public array $warnings = [];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> attributes for the entry
     *
     * @throws ValidationException
     */
    protected function validateMeasurement(User $actor, Project $project, array $input, ?MeasurementEntry $entry): array
    {
        if (! $project->isOpen()) {
            throw ValidationException::withMessages(['project' => __('Measurements cannot be recorded on a :status project.', ['status' => $project->status->name])]);
        }

        $data = array_map(function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            $value = trim($value);

            return $value === '' ? null : $value;
        }, $input);

        foreach (['nos', 'length', 'width', 'height', 'quantity', 'rate'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = str_replace(',', '', $data[$field]);
            }
        }

        $data['measured_by'] ??= $entry->measured_by ?? $actor->employee_id;
        $data['measured_on'] ??= today()->toDateString();

        $line = null;

        if (is_numeric($data['estimate_line_id'] ?? null)) {
            $lineId = (int) $data['estimate_line_id'];

            if ($lineId !== $entry?->estimate_line_id && ! app(MeasurementLimits::class)->isBoqLine($project, $lineId)) {
                throw ValidationException::withMessages(['estimate_line_id' => __('Pick a line of this project\'s approved estimate.')]);
            }

            $line = EstimateLine::query()->findOrFail($lineId);
            $data['work_item_id'] ??= $line->work_item_id;
            $data['description'] ??= $line->description;
            $data['unit_id'] ??= $line->unit_id;
            $data['measurement_formula'] ??= $line->measurement_formula->value;
        }

        $workItem = is_numeric($data['work_item_id'] ?? null) ? WorkItem::query()->find((int) $data['work_item_id']) : null;
        $data['unit_id'] ??= $workItem?->unit_id;
        $data['measurement_formula'] ??= $workItem->measurement_formula->value ?? MeasurementFormula::Manual->value;
        $defaultRate = $line !== null ? $line->rate : $workItem?->standard_rate;
        $data['rate'] ??= $entry->rate ?? $defaultRate;

        $validated = Validator::make($data, [
            'estimate_line_id' => ['nullable', 'integer'],
            'mb_book_no' => ['nullable', 'string', 'max:30'],
            'mb_page_no' => ['nullable', 'string', 'max:30'],
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'measured_by' => ['required', new AssignableEmployee($entry?->measured_by)],
            'work_item_id' => ['nullable', new ActiveLookup('work_items', $entry->work_item_id ?? $line?->work_item_id)],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:120'],
            'measurement_formula' => ['required', Rule::enum(MeasurementFormula::class)],
            'nos' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unit_id' => ['required', new ActiveLookup('units', $entry->unit_id ?? $line?->unit_id)],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [], ['measured_by' => __('measured by'), 'unit_id' => __('unit'), 'estimate_line_id' => __('BOQ line')])
            ->after(function (ValidatorInstance $validator) use ($data): void {
                $formula = MeasurementFormula::tryFrom((string) $data['measurement_formula']);

                foreach ($formula?->dimensions() ?? [] as $dimension) {
                    if ($dimension !== 'nos' && ($data[$dimension] ?? null) === null) {
                        $validator->errors()->add($dimension, __('The :dimension is required for this formula.', ['dimension' => __($dimension)]));
                    }
                }

                if ($formula === MeasurementFormula::Manual && ($data['quantity'] ?? null) === null) {
                    $validator->errors()->add('quantity', __('Type the measured quantity.'));
                }
            })->validate();

        $rate = (string) $validated['rate'];
        $keptRate = $entry !== null && BigDecimal::of($rate)->isEqualTo($entry->rate);

        if ($defaultRate !== null && ! $keptRate && ! BigDecimal::of($rate)->isEqualTo($defaultRate) && ! Gate::forUser($actor)->allows('editMeasurementRate', $project)) {
            throw ValidationException::withMessages(['rate' => __('Only a project manager can change the rate from :rate.', ['rate' => Money::formatRate((string) $defaultRate)])]);
        }

        $formula = MeasurementFormula::from((string) $validated['measurement_formula']);
        $quantity = QuantityCalculator::quantity($formula, $validated['nos'] ?? null, $validated['length'] ?? null, $validated['width'] ?? null, $validated['height'] ?? null, $validated['quantity'] ?? null);

        if (! BigDecimal::of($quantity)->isPositive()) {
            throw ValidationException::withMessages(['quantity' => __('The measured quantity must be more than zero.')]);
        }

        $achievement = null;
        $this->warnings = [];

        if ($line !== null) {
            $progress = app(MeasurementLimits::class)->check($line, $quantity, $entry);

            if ($progress['state'] === MeasurementLimits::BLOCK) {
                throw ValidationException::withMessages(['quantity' => __('This takes the BOQ item to :cumulative of :boq (:pct%), more than :limit% above the BOQ. Previously measured: :previous; this entry: :this. Amend the contract first (ES-BR-08).', [
                    'cumulative' => $progress['cumulative'], 'boq' => $progress['boq'], 'pct' => $progress['pct'], 'limit' => $progress['limit_pct'],
                    'previous' => $progress['previous'], 'this' => $progress['this'],
                ])]);
            }

            if ($progress['state'] === MeasurementLimits::WARN) {
                $this->warnings[] = __('The BOQ item is now at :pct% of its quantity.', ['pct' => $progress['pct']]);
            }

            $achievement = $progress['pct'];
        }

        return [
            'estimate_line_id' => $line?->id,
            'mb_book_no' => $validated['mb_book_no'] ?? null,
            'mb_page_no' => $validated['mb_page_no'] ?? null,
            'measured_on' => $validated['measured_on'],
            'measured_by' => (int) $validated['measured_by'],
            'work_item_id' => isset($validated['work_item_id']) ? (int) $validated['work_item_id'] : null,
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'measurement_formula' => $formula,
            'nos' => $validated['nos'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'unit_id' => (int) $validated['unit_id'],
            'quantity' => $quantity,
            'rate' => $rate,
            'amount' => QuantityCalculator::amount($quantity, $rate),
            'achievement_pct' => $achievement,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }
}
