<?php

namespace App\Modules\Catalog\Imports;

use App\Modules\Catalog\Actions\SaveWorkItem;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\WorkItem;
use App\Support\Imports\ImportDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Work item import (docs/02 §4): code, name, category, unit, formula, rate.
 */
final class WorkItemImport implements ImportDefinition
{
    use ResolvesLookups;

    public function __construct(private SaveWorkItem $saveWorkItem) {}

    public function columns(): array
    {
        return [
            'code' => __('Code'),
            'name' => __('Name'),
            'category' => __('Category code or name'),
            'unit' => __('Unit code, symbol or name'),
            'formula' => __('nos_l_w_h, nos_l_w, nos_l, nos or manual'),
            'rate' => __('Standard rate; blank keeps it, - clears it'),
        ];
    }

    public function requiredHeadings(): array
    {
        return ['code', 'name', 'category', 'unit', 'formula'];
    }

    public function exampleRow(): array
    {
        return ['code' => 'EW-001', 'name' => 'Earth work in excavation', 'category' => 'EARTHWORK', 'unit' => 'cum', 'formula' => 'nos_l_w_h', 'rate' => '450'];
    }

    public function findExisting(string $code): ?Model
    {
        return WorkItem::withTrashed()->where('code', $code)->first();
    }

    public function toInput(array $row, ?Model $existing): array
    {
        $errors = [];
        $input = $existing instanceof WorkItem
            ? $existing->only(SaveWorkItem::FIELDS)
            : ['name' => '', 'work_item_category_id' => null, 'unit_id' => null, 'measurement_formula' => '', 'standard_rate' => null, 'specification' => null, 'is_active' => true];

        $input['code'] = $row['code'] ?? '';

        if (($row['name'] ?? '') !== '') {
            $input['name'] = $row['name'];
        }

        if (($row['category'] ?? '') !== '') {
            $input['work_item_category_id'] = $this->resolveLookup('work_item_categories', $row['category']);

            if ($input['work_item_category_id'] === null) {
                $errors['work_item_category_id'] = __('Unknown or inactive category ":value".', ['value' => $row['category']]);
            }
        }

        if (($row['unit'] ?? '') !== '') {
            $input['unit_id'] = $this->resolveLookup('units', $row['unit'], ['code', 'symbol', 'name']);

            if ($input['unit_id'] === null) {
                $errors['unit_id'] = __('Unknown or inactive unit ":value".', ['value' => $row['unit']]);
            }
        }

        if (($row['formula'] ?? '') !== '') {
            $formula = MeasurementFormula::fromInput($row['formula']);
            $input['measurement_formula'] = $formula->value ?? '';

            if ($formula === null) {
                $errors['measurement_formula'] = __('Unknown formula ":value".', ['value' => $row['formula']]);
            }
        }

        $rate = $row['rate'] ?? '';

        if ($rate === '-') {
            $input['standard_rate'] = null;
        } elseif ($rate !== '') {
            $input['standard_rate'] = $rate;
        }

        return ['input' => $input, 'errors' => $errors];
    }

    public function validator(array $input, ?Model $existing): Validator
    {
        return $this->saveWorkItem->validator($input, $existing instanceof WorkItem ? $existing : null);
    }

    /**
     * A deleted work item keeps its code, so importing that code restores it.
     */
    public function save(array $input, ?Model $existing): Model
    {
        $workItem = $this->saveWorkItem->handle($input, $existing instanceof WorkItem ? $existing : null);

        if ($workItem->trashed()) {
            $workItem->restore();
        }

        return $workItem;
    }
}
