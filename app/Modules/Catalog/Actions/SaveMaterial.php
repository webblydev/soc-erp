<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Concerns\NormalisesCatalogInput;
use App\Modules\Catalog\Models\Material;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Creates or edits a material (docs/02 §3.8, CT-BR-05). Used by the form and the import.
 */
class SaveMaterial
{
    use NormalisesCatalogInput;

    public const FIELDS = ['code', 'name', 'material_category_id', 'unit_id', 'standard_rate', 'is_active'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Material $material = null): Material
    {
        /** @var array<string, mixed> $data */
        $data = $this->validator($input, $material)->validate();

        return DB::transaction(function () use ($data, $material): Material {
            $material ??= new Material;
            $material->fill(Arr::only($data, self::FIELDS));
            $material->save();

            return $material;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validator(array $input, ?Material $material = null): Validator
    {
        $input = $this->normalise(Arr::only($input, self::FIELDS), ['standard_rate']);

        return ValidatorFactory::make($input, [
            'code' => ['required', 'string', 'max:40', 'regex:'.SaveService::CODE_PATTERN, Rule::unique('materials', 'code')->ignore($material?->id)],
            'name' => ['required', 'string', 'max:200'],
            'material_category_id' => ['required', new ActiveLookup('material_categories', $material?->material_category_id)],
            'unit_id' => ['required', new ActiveLookup('units', $material?->unit_id)],
            'standard_rate' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'is_active' => ['boolean'],
        ], [], [
            'material_category_id' => __('category'),
            'unit_id' => __('unit'),
        ]);
    }
}
