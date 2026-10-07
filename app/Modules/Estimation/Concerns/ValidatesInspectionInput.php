<?php

namespace App\Modules\Estimation\Concerns;

use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Rules\AssignableEmployee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Support\Lookups\ActiveLookup;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Inspection header and finding rules (docs/05 §3.8, §3.9, ES-BR-11, ES-BR-13, spec E15, E16).
 */
trait ValidatesInspectionInput
{
    private const INSPECTION_FIELDS = [
        'inspection_type_id', 'inspection_date', 'start_time', 'end_time', 'site_address', 'permittee_name', 'contractor_name',
        'project_engineer_id', 'field_office_phone', 'weather', 'workers_on_site', 'work_progress_summary', 'client_representative',
    ];

    private const FINDING_FIELDS = [
        'location', 'description', 'finding', 'finding_category_id', 'finding_severity_id', 'action_required', 'responsible_type',
        'responsible_id', 'due_date', 'found_by_name',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateInspection(Project $project, array $input, ?SiteInspection $inspection): array
    {
        $data = [];

        foreach (self::INSPECTION_FIELDS as $field) {
            $data[$field] = self::blankToNull($input[$field] ?? null);
        }

        foreach (['start_time', 'end_time'] as $field) {
            if (is_string($data[$field]) && strlen($data[$field]) === 8) {
                $data[$field] = substr($data[$field], 0, 5);
            }
        }

        $validated = Validator::make($data, [
            'inspection_type_id' => ['required', new ActiveLookup('inspection_types', $inspection?->inspection_type_id)],
            'inspection_date' => ['required', 'date', 'before_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', ...($data['start_time'] !== null ? ['after:start_time'] : [])],
            'site_address' => ['nullable', 'string', 'max:255'],
            'permittee_name' => ['nullable', 'string', 'max:150'],
            'contractor_name' => ['nullable', 'string', 'max:150'],
            'project_engineer_id' => ['nullable', new AssignableEmployee($inspection?->project_engineer_id)],
            'field_office_phone' => ['nullable', 'string', 'max:30'],
            'weather' => ['nullable', 'string', 'max:60'],
            'workers_on_site' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'work_progress_summary' => ['nullable', 'string', 'max:5000'],
            'client_representative' => ['nullable', 'string', 'max:150'],
        ], [], ['inspection_type_id' => __('inspection type'), 'project_engineer_id' => __('project engineer')])->validate();

        $type = InspectionType::query()->findOrFail((int) $validated['inspection_type_id']);
        $this->ensureProjectAllows($project, $type);

        if (Phone::isValid($validated['field_office_phone'] ?? null)) {
            $validated['field_office_phone'] = Phone::normalise($validated['field_office_phone']);
        }

        return $validated;
    }

    /**
     * Closed projects take only HANDOVER / SNAG inspections, and only once completed (ES-BR-13).
     *
     * @throws ValidationException
     */
    protected function ensureProjectAllows(Project $project, InspectionType $type): void
    {
        if ($project->isOpen()) {
            return;
        }

        if (! $type->allowed_after_completion || $project->status->code === ProjectStatus::CANCELLED) {
            throw ValidationException::withMessages(['inspection_type_id' => __('A :status project takes only handover or snag inspections.', ['status' => $project->status->name])]);
        }
    }

    /**
     * @param  array<int, mixed>  $findings
     * @param  array<int, int>  $existingIds  finding ids that may be posted back
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    protected function validateFindings(Project $project, array $findings, array $existingIds = [], string $key = 'findings'): array
    {
        $rows = array_values(array_map(function (array $finding): array {
            $row = ['id' => is_numeric($finding['id'] ?? null) ? (int) $finding['id'] : null];

            foreach (self::FINDING_FIELDS as $field) {
                $row[$field] = self::blankToNull($finding[$field] ?? null);
            }

            return $row;
        }, array_filter($findings, 'is_array')));

        $followUp = FindingSeverity::query()->where('requires_follow_up', true)->pluck('id')->all();

        $validator = Validator::make([$key => $rows], [
            $key => ['array', 'max:100'],
            "{$key}.*.location" => ['nullable', 'string', 'max:150'],
            "{$key}.*.description" => ['required', 'string', 'max:5000'],
            "{$key}.*.finding" => ['nullable', 'string', 'max:5000'],
            "{$key}.*.finding_category_id" => ['nullable', new ActiveLookup('finding_categories')],
            "{$key}.*.finding_severity_id" => ['required', new ActiveLookup('finding_severities')],
            "{$key}.*.action_required" => ['nullable', 'string', 'max:5000'],
            "{$key}.*.responsible_type" => ['nullable', Rule::in([SiteInspectionFinding::RESPONSIBLE_EMPLOYEE, SiteInspectionFinding::RESPONSIBLE_CUSTOMER, SiteInspectionFinding::RESPONSIBLE_CONTRACTOR])],
            "{$key}.*.due_date" => ['nullable', 'date'],
            "{$key}.*.found_by_name" => ['nullable', 'string', 'max:150'],
        ], [], [
            "{$key}.*.description" => __('description'),
            "{$key}.*.finding_severity_id" => __('severity'),
            "{$key}.*.responsible_type" => __('responsible'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($rows, $existingIds, $followUp, $project, $key): void {
            foreach ($rows as $index => $row) {
                $at = "{$key}.{$index}";

                if ($row['id'] !== null && ! in_array($row['id'], $existingIds, true)) {
                    $validator->errors()->add("{$at}.id", __('This finding does not belong to the inspection.'));
                }

                if ($row['responsible_type'] === SiteInspectionFinding::RESPONSIBLE_EMPLOYEE
                    && (! is_numeric($row['responsible_id']) || ! Employee::query()->assignable()->whereKey((int) $row['responsible_id'])->exists())) {
                    $validator->errors()->add("{$at}.responsible_id", __('Choose an active employee.'));
                }

                if ($row['responsible_type'] === SiteInspectionFinding::RESPONSIBLE_CUSTOMER && $project->customer_id === null) {
                    $validator->errors()->add("{$at}.responsible_type", __('This project has no customer.'));
                }

                if (in_array((int) $row['finding_severity_id'], $followUp, true)) {
                    if ($row['responsible_type'] === null) {
                        $validator->errors()->add("{$at}.responsible_type", __('High and critical findings need someone responsible (ES-BR-11).'));
                    }

                    if ($row['due_date'] === null) {
                        $validator->errors()->add("{$at}.due_date", __('High and critical findings need a due date (ES-BR-11).'));
                    }
                }
            }
        });

        $validator->validate();

        return array_map(fn (array $row): array => [
            ...$row,
            'finding_category_id' => $row['finding_category_id'] !== null ? (int) $row['finding_category_id'] : null,
            'finding_severity_id' => (int) $row['finding_severity_id'],
            'responsible_id' => match ($row['responsible_type']) {
                SiteInspectionFinding::RESPONSIBLE_EMPLOYEE => (int) $row['responsible_id'],
                SiteInspectionFinding::RESPONSIBLE_CUSTOMER => $project->customer_id,
                default => null,
            },
        ], $rows);
    }

    private static function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
