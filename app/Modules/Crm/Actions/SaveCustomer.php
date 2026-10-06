<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Events\CustomerCreated;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerStatus;
use App\Support\AuditTrail\AuditTrail;
use App\Support\Lookups\ActiveLookup;
use App\Support\NumberSequenceService;
use App\Support\Phone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a customer and its contacts (docs/03 §3.9–3.10, CRM-BR-12, spec R15).
 */
class SaveCustomer
{
    private const FIELDS = [
        'customer_type_id', 'name', 'company_name', 'phone', 'alternate_phone', 'whatsapp', 'email', 'address', 'location_id',
        'nid_or_reg_no', 'business_line_id', 'account_manager_user_id', 'customer_status_id', 'is_also_vendor', 'notes',
    ];

    private const PHONE_FIELDS = ['phone', 'alternate_phone', 'whatsapp'];

    private const FINANCE_FIELDS = ['payment_term_id', 'credit_limit', 'tin', 'bin'];

    private const ATTRIBUTION_FIELDS = ['source_lead_id', 'lead_source_id', 'acquired_by_user_id'];

    public function __construct(private FindDuplicates $findDuplicates, private NumberSequenceService $numbers) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{source_lead_id?: int, lead_source_id?: int, acquired_by_user_id?: int|null}  $attribution
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?Customer $customer = null, array $attribution = []): Customer
    {
        Gate::forUser($actor)->authorize($customer === null ? 'create' : 'update', $customer ?? Customer::class);

        $canEditFinance = $actor->can('crm.customers.update_finance');
        $data = $this->validate($this->normalise($input), $customer);

        $matches = $this->duplicateMatches($data, $customer);
        $reason = trim((string) ($data['duplicate_reason'] ?? ''));

        if ($matches->isNotEmpty() && $reason === '') {
            throw ValidationException::withMessages(['duplicate_reason' => __('Another customer already uses this number: :list. Give a reason to save anyway.', [
                'list' => $matches->map(fn (DuplicateMatch $match): string => "{$match->number} {$match->name}")->implode(', '),
            ])]);
        }

        return DB::transaction(function () use ($actor, $data, $customer, $attribution, $canEditFinance, $matches, $reason): Customer {
            $isNew = $customer === null;
            $customer ??= new Customer;
            $customer->fill(Arr::only($data, self::FIELDS));
            $customer->customer_status_id ??= CustomerStatus::idFor(CustomerStatus::ACTIVE);

            if ($canEditFinance) {
                $customer->forceFill(Arr::only($data, self::FINANCE_FIELDS));
            }

            if ($isNew) {
                $customer->forceFill(['customer_number' => $this->numbers->next('customer'), ...Arr::only($attribution, self::ATTRIBUTION_FIELDS)]);
            }

            $customer->save();
            $this->syncContacts($customer, $data['contacts'] ?? []);

            if ($matches->isNotEmpty()) {
                AuditTrail::record($customer, 'duplicate_override', null, ['reason' => $reason, 'matches' => $matches->pluck('number')->all()], $actor);
            }

            if ($isNew) {
                CustomerCreated::dispatch($customer);
            }

            return $customer;
        });
    }

    /**
     * Other customers sharing a phone (CRM-BR-12). An edit that keeps the same phones is not
     * checked again, so a customer saved once with a reason is not asked for one on every edit.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, DuplicateMatch>
     */
    private function duplicateMatches(array $data, ?Customer $customer): Collection
    {
        $phones = array_map(fn (string $field): mixed => $data[$field] ?? null, self::PHONE_FIELDS);

        if ($customer !== null && $phones === array_map(fn (string $field): mixed => $customer->getAttribute($field), self::PHONE_FIELDS)) {
            return collect();
        }

        return collect($this->findDuplicates->handle(Arr::only($data, self::PHONE_FIELDS), ignoreCustomer: $customer))
            ->where('type', 'customer')->values();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        foreach (self::PHONE_FIELDS as $key) {
            $input[$key] = Phone::normalise(is_string($input[$key] ?? null) ? $input[$key] : null);
        }

        $input['email'] = is_string($input['email'] ?? null) && trim($input['email']) !== '' ? Str::lower(trim($input['email'])) : null;
        $input['credit_limit'] = is_string($input['credit_limit'] ?? null) ? (str_replace(',', '', trim($input['credit_limit'])) ?: null) : ($input['credit_limit'] ?? null);

        foreach (['company_name', 'address', 'location_id', 'nid_or_reg_no', 'business_line_id', 'account_manager_user_id', 'customer_status_id', 'notes', 'payment_term_id', 'tin', 'bin'] as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }

        $input['contacts'] = array_values(array_map(fn (array $contact): array => [
            ...$contact,
            'phone' => Phone::normalise(is_string($contact['phone'] ?? null) ? $contact['phone'] : null),
            'is_primary' => (bool) ($contact['is_primary'] ?? false),
        ], is_array($input['contacts'] ?? null) ? $input['contacts'] : []));

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $input, ?Customer $customer): array
    {
        $validator = Validator::make($input, [
            'customer_type_id' => ['required', new ActiveLookup('customer_types', $customer?->customer_type_id)],
            'name' => ['required', 'string', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['required', Phone::rule()],
            'alternate_phone' => ['nullable', Phone::rule()],
            'whatsapp' => ['nullable', Phone::rule()],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer', ...(($input['location_id'] ?? null) == $customer?->location_id ? [] : [Rule::exists('locations', 'id')->where('is_active', true)])],
            'nid_or_reg_no' => ['nullable', 'string', 'max:40'],
            'business_line_id' => ['nullable', new ActiveLookup('business_lines', $customer?->business_line_id, fn (Builder $query) => $query->where('is_internal', false))],
            'account_manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'customer_status_id' => ['nullable', new ActiveLookup('customer_statuses', $customer?->customer_status_id)],
            'is_also_vendor' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_term_id' => ['nullable', new ActiveLookup('payment_terms', $customer?->payment_term_id)],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'tin' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'contacts' => ['array', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer', Rule::exists('customer_contacts', 'id')->where('customer_id', $customer->id ?? 0)->whereNull('deleted_at')],
            'contacts.*.name' => ['required', 'string', 'max:150'],
            'contacts.*.designation' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', Phone::rule()],
            'contacts.*.email' => ['nullable', 'email', 'max:150'],
            'contacts.*.is_primary' => ['boolean'],
            'contacts.*.notes' => ['nullable', 'string', 'max:255'],
            'duplicate_reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'customer_type_id' => __('customer type'),
            'location_id' => __('location'),
            'business_line_id' => __('business line'),
            'account_manager_user_id' => __('account manager'),
            'customer_status_id' => __('status'),
            'payment_term_id' => __('payment term'),
            'contacts.*.name' => __('contact name'),
            'contacts.*.phone' => __('contact phone'),
        ]);

        $validator->after(function ($validator) use ($input): void {
            if (count(array_filter((array) $input['contacts'], fn (array $contact): bool => $contact['is_primary'] === true)) > 1) {
                $validator->errors()->add('contacts', __('Only one contact can be primary.'));
            }
        });

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     */
    private function syncContacts(Customer $customer, array $contacts): void
    {
        $keep = [];

        foreach ($contacts as $contact) {
            $attributes = Arr::only($contact, ['name', 'designation', 'phone', 'email', 'is_primary', 'notes']);

            if (isset($contact['id'])) {
                $row = $customer->contacts()->whereKey((int) $contact['id'])->firstOrFail();
                $row->update($attributes);
            } else {
                $row = $customer->contacts()->create($attributes);
            }

            $keep[] = $row->id;
        }

        $customer->contacts()->whereNotIn('id', $keep)->get()->each->delete();
    }
}
