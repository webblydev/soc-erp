<?php

namespace App\Modules\Crm\Livewire\Customers;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Actions\SaveCustomer;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerContact;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Create / edit a customer with its contacts (docs/03 §5.10). Finance fields are read-only
 * without crm.customers.update_finance (spec R15).
 */
class Form extends Component
{
    use SavesFromDetailModal;

    public ?Customer $customer = null;

    public int|string|null $customer_type_id = null;

    public string $name = '';

    public string $company_name = '';

    public string $nid_or_reg_no = '';

    public string $phone = '';

    public string $alternate_phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $address = '';

    public int|string|null $location_id = null;

    public int|string|null $business_line_id = null;

    public int|string|null $account_manager_user_id = null;

    public int|string|null $customer_status_id = null;

    public bool $is_also_vendor = false;

    public int|string|null $payment_term_id = null;

    public string $credit_limit = '';

    public string $tin = '';

    public string $bin = '';

    public string $notes = '';

    /** @var list<array<string, mixed>> */
    public array $contacts = [];

    public string $duplicate_reason = '';

    public bool $canEditFinance = false;

    public function mount(?Customer $customer = null): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->canEditFinance = $actor->can('crm.customers.update_finance');

        if ($customer === null || ! $customer->exists) {
            $this->authorize('create', Customer::class);
            $this->customer_type_id = CustomerType::query()->where('code', 'INDIVIDUAL')->value('id');
            $this->account_manager_user_id = $actor->can('crm.customers.view') ? $actor->id : null;

            return;
        }

        $this->authorize('update', $customer);

        $this->customer = $customer;
        $this->fill($customer->only(['customer_type_id', 'location_id', 'business_line_id', 'account_manager_user_id', 'customer_status_id', 'payment_term_id']));
        $this->is_also_vendor = (bool) $customer->is_also_vendor;

        foreach (['name', 'company_name', 'nid_or_reg_no', 'phone', 'alternate_phone', 'whatsapp', 'email', 'address', 'tin', 'bin', 'notes'] as $field) {
            $this->{$field} = (string) $customer->getAttribute($field);
        }

        $this->credit_limit = $customer->credit_limit !== null ? rtrim(rtrim($customer->credit_limit, '0'), '.') : '';
        $this->contacts = array_values($customer->contacts()->get()->map(fn (CustomerContact $contact): array => [
            'id' => $contact->id,
            'name' => $contact->name,
            'designation' => (string) $contact->designation,
            'phone' => (string) $contact->phone,
            'email' => (string) $contact->email,
            'is_primary' => $contact->is_primary,
            'notes' => (string) $contact->notes,
        ])->all());
    }

    public function addContact(): void
    {
        $this->contacts[] = ['id' => null, 'name' => '', 'designation' => '', 'phone' => '', 'email' => '', 'is_primary' => $this->contacts === [], 'notes' => ''];
    }

    public function removeContact(int $index): void
    {
        $this->contacts = array_values(array_filter($this->contacts, fn (int $i): bool => $i !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function makePrimary(int $index): void
    {
        foreach (array_keys($this->contacts) as $i) {
            $this->contacts[$i]['is_primary'] = $i === $index;
        }
    }

    public function save(SaveCustomer $saveCustomer): void
    {
        $this->customer === null ? $this->authorize('create', Customer::class) : $this->authorize('update', $this->customer);
        $this->resetErrorBag();

        /** @var User $actor */
        $actor = auth()->user();

        $customer = $saveCustomer->handle($actor, [
            ...$this->only([
                'customer_type_id', 'name', 'company_name', 'phone', 'alternate_phone', 'whatsapp', 'email', 'address', 'location_id',
                'nid_or_reg_no', 'business_line_id', 'account_manager_user_id', 'customer_status_id', 'is_also_vendor', 'notes',
                'payment_term_id', 'credit_limit', 'tin', 'bin', 'contacts',
            ]),
            'duplicate_reason' => $this->duplicate_reason !== '' ? $this->duplicate_reason : null,
        ], $this->customer);

        $this->redirectAfterSave($this->customer === null ? __('Customer created.') : __('Customer saved.'), 'crm.customers.show', $customer);
    }

    public function render(): View
    {
        return view('livewire.crm.customers.form', [
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'businessLines' => BusinessLine::query()
                ->where(fn ($query) => $query->where('is_active', true)->where('is_internal', false)->orWhere('id', $this->customer?->business_line_id))
                ->ordered()->get(['id', 'name']),
        ])
            ->title($this->customer === null ? __('New customer') : __('Edit customer'))
            ->layoutData(['back' => route('crm.customers.index'), 'bottomNav' => false]);
    }
}
