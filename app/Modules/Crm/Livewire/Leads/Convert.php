<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Actions\ConvertLead;
use App\Modules\Crm\Actions\FindDuplicates;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Lead → customer conversion wizard (docs/03 §5.7, spec R1): 1 Customer, 2 Review & confirm.
 * The Projects step arrives with 04.
 */
class Convert extends Component
{
    /** Fields of step 1 "new customer"; errors on them send the user back to step 1. */
    private const CUSTOMER_FIELDS = [
        'customer_type_id', 'name', 'company_name', 'phone', 'whatsapp', 'alternate_phone', 'email', 'address',
        'location_id', 'nid_or_reg_no', 'business_line_id', 'account_manager_user_id', 'tin', 'payment_term_id',
    ];

    public Lead $lead;

    public int $step = 1;

    public string $choice = 'link';

    public int|string|null $customerId = null;

    public string $search = '';

    /** @var array<string, mixed> */
    public array $customer = [];

    public string $duplicate_reason = '';

    public function mount(Lead $lead): void
    {
        $this->authorize('convert', $lead);
        $this->lead = $lead->load('services.service');

        $suggested = $this->suggestions();
        $this->customerId = $suggested->first(fn (Customer $customer): bool => ! $customer->isBlocked())?->id;
        $this->choice = $this->customerId === null ? 'new' : 'link';

        $this->customer = [
            'customer_type_id' => CustomerType::query()->where('code', $lead->company_name ? 'COMPANY' : 'INDIVIDUAL')->value('id'),
            'name' => $lead->name,
            'company_name' => (string) $lead->company_name,
            'phone' => $lead->phone,
            'whatsapp' => (string) $lead->whatsapp,
            'alternate_phone' => (string) $lead->office_phone,
            'email' => (string) $lead->email,
            'address' => (string) $lead->address,
            'location_id' => $lead->location_id,
            'nid_or_reg_no' => '',
            'business_line_id' => $lead->business_line_id,
            'account_manager_user_id' => $lead->assigned_to,
            'tin' => '',
            'payment_term_id' => null,
            'contacts' => [],
        ];
    }

    public function pick(int $customerId): void
    {
        $customer = Customer::query()->whereNull('merged_into_id')->with('status')->findOrFail($customerId);
        abort_if($customer->isBlocked(), 422);

        $this->customerId = $customer->id;
        $this->choice = 'link';
    }

    public function next(): void
    {
        $this->resetErrorBag();

        if ($this->choice === 'link') {
            $this->validate(['customerId' => ['required', 'integer']], [], ['customerId' => __('customer')]);
        } else {
            $this->validate([
                'customer.customer_type_id' => ['required'],
                'customer.name' => ['required', 'string', 'max:200'],
                'customer.phone' => ['required', 'string'],
            ], [], [
                'customer.customer_type_id' => __('customer type'),
                'customer.name' => __('name'),
                'customer.phone' => __('phone'),
            ]);
        }

        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function convert(ConvertLead $convertLead): void
    {
        $this->authorize('convert', $this->lead);
        $this->resetErrorBag();

        $input = $this->choice === 'link'
            ? ['customer_id' => $this->customerId]
            : ['customer' => [...$this->customer, 'duplicate_reason' => $this->duplicate_reason !== '' ? $this->duplicate_reason : null]];

        try {
            $customer = $convertLead->handle($this->actor(), $this->lead, $input);
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [
                in_array(strtok($key, '.'), [...self::CUSTOMER_FIELDS, 'contacts'], true) ? "customer.{$key}" : $key => $messages,
            ]);

            if ($errors->keys()->contains(fn (string $key): bool => str_starts_with($key, 'customer') || $key === 'duplicate_reason')) {
                $this->step = 1;
            }

            throw ValidationException::withMessages($errors->all());
        }

        session()->flash('success', __('Lead converted to :number.', ['number' => $customer->customer_number]));

        $this->actor()->can('view', $customer)
            ? $this->redirectRoute('crm.customers.show', $customer, navigate: true)
            : $this->redirectRoute('crm.leads.show', $this->lead, navigate: true);
    }

    /**
     * Customers that share a phone or email with the lead, plus the customer it refers to (first).
     * Blocked ones are listed but cannot be picked.
     *
     * @return Collection<int, Customer>
     */
    private function suggestions(): Collection
    {
        $ids = collect(app(FindDuplicates::class)->handle($this->lead->only(['phone', 'whatsapp', 'office_phone', 'email'])))
            ->where('type', 'customer')->pluck('id');

        if ($this->lead->referrer_type === 'customer' && $this->lead->referrer_id !== null) {
            $ids->prepend($this->lead->referrer_id);
        }

        $customers = Customer::query()->whereNull('merged_into_id')->with('status')->whereKey($ids->unique()->all())->get();

        return $ids->unique()->map(fn (int $id): ?Customer => $customers->firstWhere('id', $id))->filter()->values();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $term = trim($this->search);
        $suggestions = $this->suggestions();
        $results = mb_strlen($term) >= 2
            ? Customer::query()->whereNull('merged_into_id')->with('status')
                ->where(fn ($query) => $query->where('customer_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                ->orderBy('name')->limit(10)->get()
            : collect();
        $linked = $this->customerId ? Customer::query()->with('status')->find($this->customerId) : null;

        return view('livewire.crm.leads.convert', [
            'suggestions' => $suggestions,
            'results' => $results,
            'linked' => $linked,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'businessLines' => BusinessLine::query()->where('is_active', true)->where('is_internal', false)->ordered()->get(['id', 'name']),
            'canEditFinance' => $this->actor()->can('crm.customers.update_finance'),
        ])
            ->title(__('Convert :number', ['number' => $this->lead->lead_number]))
            ->layoutData(['back' => route('crm.leads.show', $this->lead), 'bottomNav' => false]);
    }
}
