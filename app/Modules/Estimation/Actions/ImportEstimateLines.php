<?php

namespace App\Modules\Estimation\Actions;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Models\CostCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads work lines from an Excel file in the EstimateLinesExport layout into SaveEstimate's input
 * shape (spec E20). Consecutive rows with the same section name form a section. Unknown work item
 * codes, units, formulas or categories are reported by row and nothing is imported.
 */
class ImportEstimateLines
{
    /**
     * @return array{sections: list<array<string, mixed>>, material_lines: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function read(string $path): array
    {
        $rows = Excel::toArray(new class implements ToArray, WithHeadingRow
        {
            /**
             * @param  array<int, array<string, mixed>>  $array
             */
            public function array(array $array): void {}
        }, $path)[0] ?? [];
        $errors = [];
        $sections = [];

        foreach ($rows as $index => $row) {
            $row = array_map(fn (mixed $value): ?string => $value === null || trim((string) $value) === '' ? null : trim((string) $value), $row);

            if (array_filter($row) === []) {
                continue;
            }

            $rowNumber = $index + 2;
            $item = isset($row['work_item_code']) ? WorkItem::query()->where('code', $row['work_item_code'])->first() : null;
            $unit = isset($row['unit']) ? Unit::query()->where(fn ($query) => $query->whereRaw('lower(code) = ?', [Str::lower($row['unit'])])
                ->orWhereRaw('lower(symbol) = ?', [Str::lower($row['unit'])])->orWhereRaw('lower(name) = ?', [Str::lower($row['unit'])]))->first() : $item?->unit;
            $formula = isset($row['formula']) ? MeasurementFormula::fromInput($row['formula']) : ($item->measurement_formula ?? MeasurementFormula::NosLWH);
            $category = isset($row['cost_category']) ? CostCategory::query()->where(fn ($query) => $query->where('code', Str::upper($row['cost_category']))->orWhere('name', $row['cost_category']))->first() : null;

            $problems = array_filter([
                isset($row['work_item_code']) && $item === null ? __('unknown work item ":code"', ['code' => $row['work_item_code']]) : null,
                $unit === null ? __('unknown or missing unit') : null,
                $formula === null ? __('unknown formula ":formula"', ['formula' => $row['formula']]) : null,
                isset($row['cost_category']) && $category === null ? __('unknown cost category ":category"', ['category' => $row['cost_category']]) : null,
                ($row['description'] ?? $item?->name) === null ? __('no description') : null,
            ]);

            if ($problems !== []) {
                $errors[] = __('Row :row: :problems.', ['row' => $rowNumber, 'problems' => implode(', ', $problems)]);

                continue;
            }

            $name = $row['section'] ?? null;

            if ($sections === [] || end($sections)['name'] !== $name) {
                $sections[] = ['name' => $name, 'lines' => []];
            }

            $sections[array_key_last($sections)]['lines'][] = [
                'line_no' => $row['line_no'] ?? null,
                'work_item_id' => $item?->id,
                'description' => $row['description'] ?? $item?->name,
                'level' => $row['level'] ?? null,
                'location' => $row['location'] ?? null,
                'measurement_formula' => $formula?->value,
                'nos' => $row['nos'] ?? null,
                'length' => $row['length'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'deduction' => in_array(Str::lower((string) ($row['deduct'] ?? '')), ['yes', 'y', '1', 'true'], true),
                'unit_id' => $unit?->id,
                'quantity' => $formula === MeasurementFormula::Manual ? ($row['quantity'] ?? null) : null,
                'rate' => $row['rate'] ?? ($item !== null ? $item->standard_rate : null),
                'cost_category_id' => $category?->id,
                'remarks' => $row['remarks'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['importFile' => $errors]);
        }

        if ($sections === []) {
            throw ValidationException::withMessages(['importFile' => __('The file has no lines.')]);
        }

        return ['sections' => $sections, 'material_lines' => []];
    }
}
