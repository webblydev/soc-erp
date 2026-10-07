<?php

namespace App\Modules\Projects\Concerns;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Models\Customer;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectType;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Project field rules shared by CreateProject and UpdateProject (PRJ-BR-02, PRJ-BR-10, spec P4, P6).
 */
trait ValidatesProjectInput
{
    /**
     * Fields a user may type on the project form.
     */
    private const PROJECT_FIELDS = [
        'name', 'customer_id', 'business_line_id', 'project_type_id', 'branch_id', 'description', 'site_address', 'location_id',
        'latitude', 'longitude', 'plot_no', 'land_area', 'land_area_unit_id', 'floors', 'basements', 'built_up_area_sft',
        'project_manager_id', 'supervisor_id', 'support_officer_id', 'start_date', 'expected_end_date', 'handover_date',
        'retention_pct', 'notes',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normaliseProject(array $input): array
    {
        $data = [];

        foreach (self::PROJECT_FIELDS as $field) {
            $value = $input[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value) === '' ? null : trim($value);
            }

            $data[$field] = $value;
        }

        foreach (['land_area', 'built_up_area_sft', 'retention_pct'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = str_replace(',', '', $data[$field]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateProject(array $input, ?Project $project): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer'],
            'business_line_id' => $project === null ? ['required', new ActiveLookup('business_lines')] : ['nullable'],
            'project_type_id' => ['required', new ActiveLookup('project_types', $project?->project_type_id)],
            'branch_id' => ['nullable', new ActiveLookup('branches', $project?->branch_id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'site_address' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer', ...(($input['location_id'] ?? null) == $project?->location_id ? [] : [Rule::exists('locations', 'id')->where('is_active', true)])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'plot_no' => ['nullable', 'string', 'max:60'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'land_area_unit_id' => ['nullable', new ActiveLookup('units', $project?->land_area_unit_id)],
            'floors' => ['nullable', 'integer', 'min:0', 'max:200'],
            'basements' => ['nullable', 'integer', 'min:0', 'max:20'],
            'built_up_area_sft' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'project_manager_id' => ['required', new AssignableEmployee($project?->project_manager_id)],
            'supervisor_id' => ['nullable', new AssignableEmployee($project?->supervisor_id)],
            'support_officer_id' => ['nullable', new AssignableEmployee($project?->support_officer_id)],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'handover_date' => ['nullable', 'date'],
            'retention_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'customer_id' => __('customer'),
            'business_line_id' => __('business line'),
            'project_type_id' => __('project type'),
            'project_manager_id' => __('project manager'),
            'supervisor_id' => __('supervisor'),
            'support_officer_id' => __('support officer'),
            'land_area_unit_id' => __('land area unit'),
            'location_id' => __('location'),
            'branch_id' => __('branch'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($input, $project): void {
            $this->validateCustomerRules($validator, $input, $project);
        });

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    /**
     * PRJ-BR-02, CRM-BR-14 and the internal business line rule (spec P4).
     *
     * @param  array<string, mixed>  $input
     */
    private function validateCustomerRules(ValidatorInstance $validator, array $input, ?Project $project): void
    {
        $type = is_numeric($input['project_type_id'] ?? null) ? ProjectType::query()->find((int) $input['project_type_id']) : null;
        $lineId = $project !== null ? $project->business_line_id : (is_numeric($input['business_line_id'] ?? null) ? (int) $input['business_line_id'] : null);
        $line = $lineId !== null ? BusinessLine::query()->find($lineId) : null;

        if ($type === null) {
            return;
        }

        if ($line !== null && $line->is_internal !== $type->is_internal) {
            $validator->errors()->add('project_type_id', $line->is_internal
                ? __('An internal business line needs an internal project type.')
                : __('An internal project type needs an internal business line.'));
        }

        if ($type->is_internal) {
            if (! empty($input['customer_id'])) {
                $validator->errors()->add('customer_id', __('Internal projects have no customer.'));
            }

            return;
        }

        if (empty($input['customer_id'])) {
            $validator->errors()->add('customer_id', __('Choose the customer for this project.'));

            return;
        }

        $customer = Customer::query()->with('status')->find((int) $input['customer_id']);
        $unchanged = $project !== null && (int) $input['customer_id'] === $project->customer_id;

        if ($customer === null || $customer->merged_into_id !== null || (! $unchanged && $customer->isBlocked())) {
            $validator->errors()->add('customer_id', __('Choose an active customer that is not blocked.'));
        }
    }
}
