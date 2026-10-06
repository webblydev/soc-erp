<?php

namespace App\Modules\Crm\Concerns;

use App\Models\User;
use App\Modules\Crm\Actions\DuplicateMatch;
use App\Modules\Crm\Actions\FindDuplicates;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Hrm\Models\Employee;
use App\Support\AuditTrail\AuditTrail;
use App\Support\Lookups\ActiveLookup;
use App\Support\Phone;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Lead field rules shared by CreateLead and UpdateLead (CRM-BR-01, 02, 04, spec R19).
 */
trait ValidatesLeadInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normaliseLead(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        foreach (['phone', 'whatsapp', 'office_phone'] as $key) {
            $input[$key] = Phone::normalise(is_string($input[$key] ?? null) ? $input[$key] : null);
        }

        $input['email'] = is_string($input['email'] ?? null) ? Str::lower($input['email']) : null;
        $input['expected_value'] = is_string($input['expected_value'] ?? null) ? str_replace(',', '', $input['expected_value']) : ($input['expected_value'] ?? null);

        $input['services'] = array_values(array_map(fn (array $line): array => [
            'service_id' => $line['service_id'] ?? null,
            'estimated_value' => is_string($line['estimated_value'] ?? null) && trim($line['estimated_value']) !== ''
                ? str_replace(',', '', trim($line['estimated_value']))
                : (is_numeric($line['estimated_value'] ?? null) ? $line['estimated_value'] : null),
            'notes' => is_string($line['notes'] ?? null) && trim($line['notes']) !== '' ? trim($line['notes']) : null,
        ], is_array($input['services'] ?? null) ? $input['services'] : []));

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $extraRules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateLead(array $input, ?Lead $lead, array $extraRules = []): array
    {
        $validator = Validator::make($input, [
            'lead_date' => ['required', 'date', 'before_or_equal:today'],
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['required', Phone::rule()],
            'whatsapp' => ['nullable', Phone::rule()],
            'office_phone' => ['nullable', Phone::rule()],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer', ...(($input['location_id'] ?? null) == $lead?->location_id ? [] : [Rule::exists('locations', 'id')->where('is_active', true)])],
            'lead_source_id' => ['required', new ActiveLookup('lead_sources', $lead?->lead_source_id)],
            'referrer_type' => ['nullable', Rule::in(Lead::REFERRER_TYPES)],
            'referrer_id' => ['nullable', 'integer'],
            'referrer_name' => ['nullable', 'string', 'max:150'],
            'business_line_id' => ['nullable', new ActiveLookup('business_lines', $lead?->business_line_id, fn (Builder $query) => $query->where('is_internal', false))],
            'lead_level_id' => ['nullable', new ActiveLookup('lead_levels', $lead?->lead_level_id)],
            'lead_priority_id' => ['required', new ActiveLookup('lead_priorities', $lead?->lead_priority_id)],
            'expected_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'expected_value_manual' => ['boolean'],
            'expected_close_date' => ['nullable', 'date'],
            'site_location_text' => ['nullable', 'string', 'max:255'],
            'land_area' => ['nullable', 'string', 'max:60'],
            'floors_planned' => ['nullable', 'integer', 'min:1', 'max:200'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['required', 'array', 'min:1', 'max:20'],
            'services.*.service_id' => ['required', 'distinct', new ActiveLookup('services')],
            'services.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'services.*.notes' => ['nullable', 'string', 'max:255'],
            ...$extraRules,
        ], [
            'services.required' => __('Choose at least one service.'),
            'services.min' => __('Choose at least one service.'),
        ], [
            'lead_source_id' => __('source'),
            'business_line_id' => __('business line'),
            'lead_level_id' => __('level'),
            'lead_priority_id' => __('priority'),
            'location_id' => __('location'),
            'services.*.service_id' => __('service'),
            'services.*.estimated_value' => __('estimated value'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($input): void {
            $this->validateReferrer($validator, $input);
        });

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    /**
     * CRM-BR-04 and referrer existence.
     *
     * @param  array<string, mixed>  $input
     */
    private function validateReferrer(ValidatorInstance $validator, array $input): void
    {
        $source = is_numeric($input['lead_source_id'] ?? null) ? LeadSource::query()->whereKey((int) $input['lead_source_id'])->first() : null;

        if ($source?->requires_referrer && empty($input['referrer_id']) && empty($input['referrer_name'])) {
            $validator->errors()->add('referrer_name', __('This source needs a referrer.'));
        }

        if (! empty($input['referrer_id'])) {
            $exists = match ($input['referrer_type'] ?? null) {
                'customer' => Customer::query()->whereNull('merged_into_id')->whereKey($input['referrer_id'])->exists(),
                'employee' => Employee::query()->whereKey($input['referrer_id'])->exists(),
                default => false,
            };

            if (! $exists) {
                $validator->errors()->add('referrer_id', __('The selected referrer is invalid.'));
            }
        }
    }

    /**
     * The expected value: the services' sum unless entered by hand (docs/03 §3.5).
     *
     * @param  array<string, mixed>  $data
     */
    protected function expectedValue(array $data): ?string
    {
        /** @var list<array{estimated_value: mixed}> $services */
        $services = $data['services'];
        $estimates = collect(array_column($services, 'estimated_value'))->filter(fn (mixed $value): bool => $value !== null);

        if (($data['expected_value_manual'] ?? false) || $estimates->isEmpty()) {
            return ($data['expected_value'] ?? null) !== null ? (string) $data['expected_value'] : null;
        }

        return (string) $estimates->reduce(fn (BigDecimal $carry, mixed $value): BigDecimal => $carry->plus((string) $value), BigDecimal::zero())->toScale(2);
    }

    /**
     * Duplicate matches other than the customer this enquiry is linked to (CRM-AC-01). Throws on
     * `duplicates` when matches remain and no reason was given (CRM-BR-03).
     *
     * @param  array<string, mixed>  $data
     * @return list<DuplicateMatch>
     *
     * @throws ValidationException
     */
    protected function guardDuplicates(FindDuplicates $finder, array $data, ?Lead $ignore = null): array
    {
        $linkedCustomer = ($data['referrer_type'] ?? null) === 'customer' ? (int) ($data['referrer_id'] ?? 0) : null;

        $matches = array_values(array_filter(
            $finder->handle(Arr::only($data, ['phone', 'whatsapp', 'office_phone', 'email']), ignoreLead: $ignore),
            fn (DuplicateMatch $match): bool => ! ($match->type === 'customer' && $match->id === $linkedCustomer),
        ));

        if ($matches !== [] && trim((string) ($data['duplicate_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['duplicates' => __('This phone or email already belongs to another lead or customer.')]);
        }

        return $matches;
    }

    /**
     * @param  list<DuplicateMatch>  $matches
     */
    protected function auditDuplicateOverride(User $actor, Lead $lead, array $matches, ?string $reason): void
    {
        if ($matches !== []) {
            AuditTrail::record($lead, 'duplicate_override', null, ['reason' => $reason, 'matches' => array_map(fn (DuplicateMatch $match): string => $match->number, $matches)], $actor);
        }
    }

    /**
     * Replace the lead's service lines with the given ones. Removed lines are soft-deleted and
     * restored if their service is picked again (one line per service).
     *
     * @param  list<array{service_id: int|string, estimated_value: ?string, notes: ?string}>  $services
     */
    protected function syncServices(Lead $lead, array $services): void
    {
        $lead->services()->whereNotIn('service_id', array_column($services, 'service_id'))->get()->each->delete();

        foreach ($services as $line) {
            $serviceLine = $lead->services()->withTrashed()->updateOrCreate(['service_id' => $line['service_id']], ['estimated_value' => $line['estimated_value'], 'notes' => $line['notes']]);

            if ($serviceLine->trashed()) {
                $serviceLine->restore();
            }
        }
    }
}
