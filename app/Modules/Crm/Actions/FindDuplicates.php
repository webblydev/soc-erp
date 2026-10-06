<?php

namespace App\Modules\Crm\Actions;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Support\Facades\Settings;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Open leads and live customers sharing a phone or email (CRM-BR-03, CRM-BR-12, spec R10).
 * Phones are compared across every phone column on both sides, after normalising.
 */
class FindDuplicates
{
    private const PHONE_INPUTS = ['phone', 'whatsapp', 'office_phone', 'alternate_phone'];

    private const LEAD_PHONE_COLUMNS = ['phone', 'whatsapp', 'office_phone'];

    private const CUSTOMER_PHONE_COLUMNS = ['phone', 'alternate_phone', 'whatsapp'];

    /**
     * @param  array<string, mixed>  $values
     * @return list<DuplicateMatch>
     */
    public function handle(array $values, ?Lead $ignoreLead = null, ?Customer $ignoreCustomer = null): array
    {
        /** @var list<string> $fields */
        $fields = (array) Settings::get('crm.duplicate_check_fields', ['phone', 'whatsapp', 'email']);

        $phones = array_intersect($fields, ['phone', 'whatsapp']) === [] ? [] : array_values(array_unique(array_filter(array_map(
            fn (string $key): ?string => Phone::normalise(is_string($values[$key] ?? null) ? $values[$key] : null),
            self::PHONE_INPUTS,
        ))));

        $email = in_array('email', $fields, true) && is_string($values['email'] ?? null) && trim($values['email']) !== ''
            ? Str::lower(trim($values['email']))
            : null;

        if ($phones === [] && $email === null) {
            return [];
        }

        $leads = Lead::query()->open()
            ->with(['status:id,name', 'assignee:id,name'])
            ->when($ignoreLead !== null, fn (Builder $query) => $query->whereKeyNot($ignoreLead?->id))
            ->where(fn (Builder $query) => $this->matching($query, self::LEAD_PHONE_COLUMNS, $phones, $email))
            ->limit(10)->get()
            ->map(fn (Lead $lead): DuplicateMatch => new DuplicateMatch('lead', $lead->id, $lead->lead_number, $lead->name, $lead->status->name, $lead->assignee?->name));

        $customers = Customer::query()->whereNull('merged_into_id')
            ->with(['status:id,name', 'accountManager:id,name'])
            ->when($ignoreCustomer !== null, fn (Builder $query) => $query->whereKeyNot($ignoreCustomer?->id))
            ->where(fn (Builder $query) => $this->matching($query, self::CUSTOMER_PHONE_COLUMNS, $phones, $email))
            ->limit(10)->get()
            ->map(fn (Customer $customer): DuplicateMatch => new DuplicateMatch('customer', $customer->id, $customer->customer_number, $customer->name, $customer->status->name, $customer->accountManager?->name));

        return [...$customers->all(), ...$leads->all()];
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<string>  $columns
     * @param  list<string>  $phones
     */
    private function matching(Builder $query, array $columns, array $phones, ?string $email): void
    {
        foreach ($phones === [] ? [] : $columns as $column) {
            $query->orWhereIn($column, $phones);
        }

        if ($email !== null) {
            $query->orWhereRaw('LOWER(email) = ?', [$email]);
        }
    }
}
