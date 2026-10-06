<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Concerns\NormalisesCatalogInput;
use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\WorkItem;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Creates or edits a work item (docs/02 §3.6, CT-BR-05). Used by the form and the import.
 */
class SaveWorkItem
{
    use NormalisesCatalogInput;

    public const FIELDS = ['code', 'name', 'work_item_category_id', 'unit_id', 'measurement_formula', 'standard_rate', 'specification', 'is_active'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?WorkItem $workItem = null): WorkItem
    {
        /** @var array<string, mixed> $data */
        $data = $this->validator($input, $workItem)->validate();

        return DB::transaction(function () use ($data, $workItem): WorkItem {
            $workItem ??= new WorkItem;
            $workItem->fill(Arr::only($data, self::FIELDS));
            $workItem->save();

            return $workItem;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validator(array $input, ?WorkItem $workItem = null): Validator
    {
        $input = $this->normalise(Arr::only($input, self::FIELDS), ['standard_rate', 'specification']);

        if (($input['measurement_formula'] ?? null) instanceof MeasurementFormula) {
            $input['measurement_formula'] = $input['measurement_formula']->value;
        }

        return ValidatorFactory::make($input, [
            'code' => ['required', 'string', 'max:40', 'regex:'.SaveService::CODE_PATTERN, Rule::unique('work_items', 'code')->ignore($workItem?->id)],
            'name' => ['required', 'string', 'max:255'],
            'work_item_category_id' => ['required', new ActiveLookup('work_item_categories', $workItem?->work_item_category_id)],
            'unit_id' => ['required', new ActiveLookup('units', $workItem?->unit_id)],
            'measurement_formula' => ['required', Rule::enum(MeasurementFormula::class)],
            'standard_rate' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'specification' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ], [], [
            'work_item_category_id' => __('category'),
            'unit_id' => __('unit'),
            'measurement_formula' => __('formula'),
        ]);
    }
}
