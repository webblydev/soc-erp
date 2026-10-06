<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Concerns\NormalisesCatalogInput;
use App\Modules\Catalog\Models\Service;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Creates or edits a service (docs/02 §3.3, CT-BR-03, CT-BR-05, spec C4/C9).
 */
class SaveService
{
    use NormalisesCatalogInput;

    public const CODE_PATTERN = '/^[A-Z0-9._-]+$/';

    private const FIELDS = ['code', 'name', 'service_category_id', 'business_line_id', 'default_unit_id', 'pricing_basis_id', 'default_rate', 'requires_approval_tracking', 'description', 'is_active'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Service $service = null): Service
    {
        /** @var array<string, mixed> $data */
        $data = $this->validator($input, $service)->validate();

        return DB::transaction(function () use ($data, $service): Service {
            $service ??= new Service;
            $service->fill(Arr::only($data, self::FIELDS));
            $service->save();

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validator(array $input, ?Service $service = null): Validator
    {
        $input = $this->normalise(Arr::only($input, self::FIELDS), ['business_line_id', 'default_unit_id', 'default_rate', 'description']);

        return ValidatorFactory::make($input, [
            'code' => ['required', 'string', 'max:40', 'regex:'.self::CODE_PATTERN, Rule::unique('services', 'code')->ignore($service?->id)],
            'name' => ['required', 'string', 'max:150'],
            'service_category_id' => ['required', new ActiveLookup('service_categories', $service?->service_category_id)],
            'business_line_id' => ['nullable', new ActiveLookup('business_lines', $service?->business_line_id, fn (Builder $query) => $query->where('is_internal', false))],
            'default_unit_id' => ['nullable', new ActiveLookup('units', $service?->default_unit_id)],
            'pricing_basis_id' => ['required', new ActiveLookup('pricing_bases', $service?->pricing_basis_id)],
            'default_rate' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'requires_approval_tracking' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [], [
            'service_category_id' => __('category'),
            'business_line_id' => __('business line'),
            'default_unit_id' => __('default unit'),
            'pricing_basis_id' => __('pricing basis'),
        ]);
    }
}
