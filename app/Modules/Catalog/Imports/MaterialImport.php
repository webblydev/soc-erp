<?php

namespace App\Modules\Catalog\Imports;

use App\Modules\Catalog\Actions\SaveMaterial;
use App\Modules\Catalog\Models\Material;
use App\Support\Imports\ImportDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Material import (docs/02 §4): code, name, category, unit, rate.
 */
final class MaterialImport implements ImportDefinition
{
    use ResolvesLookups;

    public function __construct(private SaveMaterial $saveMaterial) {}

    public function columns(): array
    {
        return [
            'code' => __('Code'),
            'name' => __('Name'),
            'category' => __('Category code or name'),
            'unit' => __('Unit code, symbol or name'),
            'rate' => __('Standard rate; blank keeps it, - clears it'),
        ];
    }

    public function requiredHeadings(): array
    {
        return ['code', 'name', 'category', 'unit'];
    }

    public function exampleRow(): array
    {
        return ['code' => 'CEM-OPC', 'name' => 'OPC cement (50 kg bag)', 'category' => 'CEMENT', 'unit' => 'bag', 'rate' => '550'];
    }

    public function findExisting(string $code): ?Model
    {
        return Material::withTrashed()->where('code', $code)->first();
    }

    public function toInput(array $row, ?Model $existing): array
    {
        $errors = [];
        $input = $existing instanceof Material
            ? $existing->only(SaveMaterial::FIELDS)
            : ['name' => '', 'material_category_id' => null, 'unit_id' => null, 'standard_rate' => null, 'is_active' => true];

        $input['code'] = $row['code'] ?? '';

        if (($row['name'] ?? '') !== '') {
            $input['name'] = $row['name'];
        }

        if (($row['category'] ?? '') !== '') {
            $input['material_category_id'] = $this->resolveLookup('material_categories', $row['category']);

            if ($input['material_category_id'] === null) {
                $errors['material_category_id'] = __('Unknown or inactive category ":value".', ['value' => $row['category']]);
            }
        }

        if (($row['unit'] ?? '') !== '') {
            $input['unit_id'] = $this->resolveLookup('units', $row['unit'], ['code', 'symbol', 'name']);

            if ($input['unit_id'] === null) {
                $errors['unit_id'] = __('Unknown or inactive unit ":value".', ['value' => $row['unit']]);
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
        return $this->saveMaterial->validator($input, $existing instanceof Material ? $existing : null);
    }

    /**
     * A deleted material keeps its code, so importing that code restores it.
     */
    public function save(array $input, ?Model $existing): Model
    {
        $material = $this->saveMaterial->handle($input, $existing instanceof Material ? $existing : null);

        if ($material->trashed()) {
            $material->restore();
        }

        return $material;
    }
}
