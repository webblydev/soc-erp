<?php

namespace App\Modules\Estimation\Concerns;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Services\QuantityCalculator;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Estimate header, section, work line and material line rules (docs/05 §3.2–3.5, spec E6–E8).
 *
 * Input shape: header fields, `sections` => [{id?, name, lines: [{id?, line_no, work_item_id, description,
 * level, location, measurement_formula, nos, length, width, height, deduction, unit_id, quantity, rate,
 * cost_category_id, remarks}]}], `material_lines` => [{id?, material_id, material_name, unit_id,
 * estimated_qty, wastage_pct, rate, purpose}].
 *
 * @phpstan-type WorkLine array{id: int|null, line_no: string, work_item_id: int|null, description: string, level: string|null, location: string|null, measurement_formula: MeasurementFormula, nos: string, length: string|null, width: string|null, height: string|null, deduction: bool, unit_id: int, quantity: string, quantity_is_manual: bool, rate: string|null, amount: string, cost_category_id: int|null, remarks: string|null, origin_line_id: int|null}
 * @phpstan-type Section array{id: int|null, name: string|null, lines: list<WorkLine>}
 * @phpstan-type MaterialLine array{id: int|null, material_id: int|null, material_name: string|null, unit_id: int, estimated_qty: string, wastage_pct: string|null, total_qty: string, rate: string|null, amount: string, purpose: string|null, origin_line_id: int|null}
 */
trait ValidatesEstimateInput
{
    private const HEADER_FIELDS = ['estimate_kind_id', 'title', 'site_address', 'estimate_date', 'prepared_by', 'checked_by', 'overhead_pct', 'profit_pct', 'vat_pct', 'notes'];

    private const LINE_LIMIT = 500;

    /**
     * @param  array<string, mixed>  $input
     * @return array{header: array<string, mixed>, sections: list<Section>, material_lines: list<MaterialLine>}
     *
     * @throws ValidationException
     */
    protected function validateEstimate(array $input, ?Estimate $estimate): array
    {
        $header = [];

        foreach (self::HEADER_FIELDS as $field) {
            $header[$field] = self::clean($input[$field] ?? null);
        }

        foreach (['overhead_pct', 'profit_pct', 'vat_pct'] as $field) {
            $header[$field] = self::number($header[$field]);
        }

        $sections = array_values(array_map(fn (mixed $section): array => [
            'id' => is_numeric($section['id'] ?? null) ? (int) $section['id'] : null,
            'name' => self::clean($section['name'] ?? null),
            'lines' => array_values(array_map(fn (mixed $line): array => self::cleanLine(is_array($line) ? $line : []), is_array($section['lines'] ?? null) ? $section['lines'] : [])),
        ], array_filter(is_array($input['sections'] ?? null) ? $input['sections'] : [], 'is_array')));

        $materials = array_values(array_map(fn (array $line): array => self::cleanMaterial($line), array_filter(is_array($input['material_lines'] ?? null) ? $input['material_lines'] : [], 'is_array')));

        $existingLines = $estimate?->lines()->get(['id', 'unit_id', 'work_item_id', 'cost_category_id', 'origin_line_id'])->keyBy('id') ?? collect();
        $existingMaterials = $estimate?->materialLines()->get(['id', 'unit_id', 'material_id', 'origin_line_id'])->keyBy('id') ?? collect();
        $existingSections = $estimate?->sections()->pluck('id')->all() ?? [];
        $kindId = $estimate->estimate_kind_id ?? $header['estimate_kind_id'];

        $rules = [
            'estimate_kind_id' => $estimate === null ? ['required', new ActiveLookup('estimate_kinds')] : ['nullable'],
            'title' => ['required', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:2000'],
            'estimate_date' => ['required', 'date'],
            'prepared_by' => ['required', new AssignableEmployee($estimate?->prepared_by)],
            'checked_by' => ['nullable', new AssignableEmployee($estimate?->checked_by)],
            'overhead_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sections' => ['array', 'max:100'],
            'sections.*.name' => ['nullable', 'string', 'max:150'],
            'sections.*.lines' => ['array', 'max:'.self::LINE_LIMIT],
            'sections.*.lines.*.line_no' => ['nullable', 'string', 'max:20'],
            'sections.*.lines.*.description' => ['required', 'string', 'max:2000'],
            'sections.*.lines.*.level' => ['nullable', 'string', 'max:60'],
            'sections.*.lines.*.location' => ['nullable', 'string', 'max:120'],
            'sections.*.lines.*.measurement_formula' => ['required', Rule::enum(MeasurementFormula::class)],
            'sections.*.lines.*.nos' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'sections.*.lines.*.length' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sections.*.lines.*.width' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sections.*.lines.*.height' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sections.*.lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'sections.*.lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'sections.*.lines.*.remarks' => ['nullable', 'string', 'max:255'],
            'material_lines' => ['array', 'max:'.self::LINE_LIMIT],
            'material_lines.*.material_name' => ['nullable', 'string', 'max:200'],
            'material_lines.*.estimated_qty' => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'material_lines.*.wastage_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'material_lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'material_lines.*.purpose' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($sections as $s => $section) {
            foreach ($section['lines'] as $l => $line) {
                $current = $line['id'] !== null ? $existingLines->get($line['id']) : null;
                $rules["sections.{$s}.lines.{$l}.unit_id"] = ['required', new ActiveLookup('units', $current?->unit_id)];
                $rules["sections.{$s}.lines.{$l}.work_item_id"] = ['nullable', new ActiveLookup('work_items', $current?->work_item_id)];
                $rules["sections.{$s}.lines.{$l}.cost_category_id"] = ['nullable', new ActiveLookup('cost_categories', $current?->cost_category_id)];
            }
        }

        foreach ($materials as $m => $line) {
            $current = $line['id'] !== null ? $existingMaterials->get($line['id']) : null;
            $rules["material_lines.{$m}.unit_id"] = ['required', new ActiveLookup('units', $current?->unit_id)];
            $rules["material_lines.{$m}.material_id"] = ['nullable', new ActiveLookup('materials', $current?->material_id)];
        }

        $validator = Validator::make([...$header, 'sections' => $sections, 'material_lines' => $materials], $rules, [], [
            'estimate_kind_id' => __('kind'),
            'prepared_by' => __('prepared by'),
            'checked_by' => __('checked by'),
            'sections.*.lines.*.description' => __('description'),
            'sections.*.lines.*.unit_id' => __('unit'),
            'sections.*.lines.*.measurement_formula' => __('formula'),
            'material_lines.*.unit_id' => __('unit'),
            'material_lines.*.estimated_qty' => __('estimated quantity'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($sections, $materials, $existingLines, $existingMaterials, $existingSections, $kindId): void {
            $kind = is_numeric($kindId) ? EstimateKind::query()->find((int) $kindId) : null;

            if ($kind !== null && ! $kind->has_work_lines && collect($sections)->contains(fn (array $section): bool => $section['lines'] !== [])) {
                $validator->errors()->add('sections', __('A :kind has no work lines.', ['kind' => $kind->name]));
            }

            if ($kind !== null && ! $kind->has_material_lines && $materials !== []) {
                $validator->errors()->add('material_lines', __('A :kind has no material lines.', ['kind' => $kind->name]));
            }

            foreach ($sections as $s => $section) {
                if ($section['id'] !== null && ! in_array($section['id'], $existingSections, true)) {
                    $validator->errors()->add("sections.{$s}.id", __('This section does not belong to the estimate.'));
                }

                foreach ($section['lines'] as $l => $line) {
                    $key = "sections.{$s}.lines.{$l}";

                    if ($line['id'] !== null && ! $existingLines->has($line['id'])) {
                        $validator->errors()->add("{$key}.id", __('This line does not belong to the estimate.'));
                    }

                    $formula = MeasurementFormula::tryFrom((string) $line['measurement_formula']);

                    foreach ($formula?->dimensions() ?? [] as $dimension) {
                        if ($dimension !== 'nos' && $line[$dimension] === null) {
                            $validator->errors()->add("{$key}.{$dimension}", __('The :dimension is required for this formula.', ['dimension' => __($dimension)]));
                        }
                    }

                    if ($formula === MeasurementFormula::Manual && $line['quantity'] === null) {
                        $validator->errors()->add("{$key}.quantity", __('Type the quantity for a manual line.'));
                    }

                    if (is_numeric($line['quantity']) && (float) $line['quantity'] < 0) {
                        $validator->errors()->add("{$key}.quantity", __('Quantities cannot be negative; tick "Deduct" instead (ES-BR-03).'));
                    }
                }
            }

            foreach ($materials as $m => $line) {
                if ($line['id'] !== null && ! $existingMaterials->has($line['id'])) {
                    $validator->errors()->add("material_lines.{$m}.id", __('This line does not belong to the estimate.'));
                }

                if ($line['material_id'] === null && $line['material_name'] === null) {
                    $validator->errors()->add("material_lines.{$m}.material_id", __('Pick a material or type its name.'));
                }
            }
        });

        $validator->validate();

        return [
            'header' => $header,
            'sections' => array_map(fn (array $section, int $s): array => [
                'id' => $section['id'],
                'name' => $section['name'],
                'lines' => array_map(fn (array $line, int $l): array => self::workLine($line, $s, $l, $existingLines->get($line['id'] ?? 0)?->origin_line_id), $section['lines'], array_keys($section['lines'])),
            ], $sections, array_keys($sections)),
            'material_lines' => array_map(fn (array $line): array => self::materialLine($line, $existingMaterials->get($line['id'] ?? 0)?->origin_line_id), $materials),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return WorkLine
     */
    private static function workLine(array $line, int $sectionIndex, int $lineIndex, ?int $originId): array
    {
        $formula = MeasurementFormula::from((string) $line['measurement_formula']);
        $nos = $line['nos'] ?? '1';
        $quantity = QuantityCalculator::quantity($formula, $nos, $line['length'], $line['width'], $line['height'], $line['quantity']);
        $deduction = (bool) $line['deduction'];

        return [
            'id' => $line['id'],
            'line_no' => $line['line_no'] ?? ($sectionIndex + 1).'.'.str_pad((string) ($lineIndex + 1), 2, '0', STR_PAD_LEFT),
            'work_item_id' => $line['work_item_id'] !== null ? (int) $line['work_item_id'] : null,
            'description' => (string) $line['description'],
            'level' => $line['level'],
            'location' => $line['location'],
            'measurement_formula' => $formula,
            'nos' => (string) $nos,
            'length' => $line['length'],
            'width' => $line['width'],
            'height' => $line['height'],
            'deduction' => $deduction,
            'unit_id' => (int) $line['unit_id'],
            'quantity' => $quantity,
            'quantity_is_manual' => $formula === MeasurementFormula::Manual,
            'rate' => $line['rate'],
            'amount' => QuantityCalculator::amount($quantity, $line['rate'], $deduction),
            'cost_category_id' => $line['cost_category_id'] !== null ? (int) $line['cost_category_id'] : null,
            'remarks' => $line['remarks'],
            'origin_line_id' => $originId,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return MaterialLine
     */
    private static function materialLine(array $line, ?int $originId): array
    {
        $total = QuantityCalculator::withWastage((string) $line['estimated_qty'], $line['wastage_pct']);

        return [
            'id' => $line['id'],
            'material_id' => $line['material_id'] !== null ? (int) $line['material_id'] : null,
            'material_name' => $line['material_id'] !== null ? null : $line['material_name'],
            'unit_id' => (int) $line['unit_id'],
            'estimated_qty' => (string) $line['estimated_qty'],
            'wastage_pct' => $line['wastage_pct'],
            'total_qty' => $total,
            'rate' => $line['rate'],
            'amount' => QuantityCalculator::amount($total, $line['rate']),
            'purpose' => $line['purpose'],
            'origin_line_id' => $originId,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function cleanLine(array $line): array
    {
        return [
            'id' => is_numeric($line['id'] ?? null) ? (int) $line['id'] : null,
            'line_no' => self::clean($line['line_no'] ?? null),
            'work_item_id' => self::clean($line['work_item_id'] ?? null),
            'description' => self::clean($line['description'] ?? null),
            'level' => self::clean($line['level'] ?? null),
            'location' => self::clean($line['location'] ?? null),
            'measurement_formula' => self::clean($line['measurement_formula'] ?? null) ?? MeasurementFormula::NosLWH->value,
            'nos' => self::number($line['nos'] ?? null),
            'length' => self::number($line['length'] ?? null),
            'width' => self::number($line['width'] ?? null),
            'height' => self::number($line['height'] ?? null),
            'deduction' => filter_var($line['deduction'] ?? false, FILTER_VALIDATE_BOOL),
            'unit_id' => self::clean($line['unit_id'] ?? null),
            'quantity' => self::number($line['quantity'] ?? null),
            'rate' => self::number($line['rate'] ?? null),
            'cost_category_id' => self::clean($line['cost_category_id'] ?? null),
            'remarks' => self::clean($line['remarks'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function cleanMaterial(array $line): array
    {
        return [
            'id' => is_numeric($line['id'] ?? null) ? (int) $line['id'] : null,
            'material_id' => self::clean($line['material_id'] ?? null),
            'material_name' => self::clean($line['material_name'] ?? null),
            'unit_id' => self::clean($line['unit_id'] ?? null),
            'estimated_qty' => self::number($line['estimated_qty'] ?? null),
            'wastage_pct' => self::number($line['wastage_pct'] ?? null),
            'rate' => self::number($line['rate'] ?? null),
            'purpose' => self::clean($line['purpose'] ?? null),
        ];
    }

    private static function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    private static function number(mixed $value): mixed
    {
        $value = self::clean($value);

        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
