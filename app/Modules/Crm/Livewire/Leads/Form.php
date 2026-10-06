<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\AssignLead;
use App\Modules\Crm\Actions\CreateLead;
use App\Modules\Crm\Actions\DuplicateMatch;
use App\Modules\Crm\Actions\FindDuplicates;
use App\Modules\Crm\Actions\UpdateLead;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\SalesTeam;
use App\Support\Facades\Settings;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * New / edit lead (docs/03 §5.2) with the live duplicate panel (CRM-BR-03, spec R10).
 */
class Form extends Component
{
    public ?Lead $lead = null;

    public string $lead_date = '';

    public string $name = '';

    public string $company_name = '';

    public string $phone = '';

    public string $whatsapp = '';

    public bool $whatsappSameAsPhone = false;

    public string $office_phone = '';

    public string $email = '';

    public string $address = '';

    public int|string|null $location_id = null;

    public int|string|null $lead_source_id = null;

    public ?string $referrer_type = null;

    public int|string|null $referrer_id = null;

    public string $referrer_name = '';

    public string $referrerSearch = '';

    public int|string|null $business_line_id = null;

    public int|string|null $lead_level_id = null;

    public int|string|null $lead_priority_id = null;

    public string $expected_value = '';

    public bool $expected_value_manual = false;

    public string $expected_close_date = '';

    public string $site_location_text = '';

    public string $land_area = '';

    public string $floors_planned = '';

    public string $notes = '';

    /** @var list<array{service_id: int|string|null, estimated_value: string, notes: string}> */
    public array $services = [];

    public int|string|null $assigned_to = null;

    public int|string|null $sales_team_id = null;

    /** @var list<array{type: string, id: int, number: string, name: string, status: string, owner: ?string}> */
    public array $duplicates = [];

    public bool $overrideDuplicates = false;

    public string $duplicate_reason = '';

    public bool $withFollowUp = false;

    public int|string|null $follow_up_type_id = null;

    public string $follow_up_at = '';

    public function mount(?Lead $lead = null): void
    {
        if ($lead === null || ! $lead->exists) {
            $this->authorize('create', Lead::class);

            $this->lead_date = today()->toDateString();
            $this->lead_priority_id = LeadPriority::query()->where('code', LeadPriority::NORMAL)->value('id');
            $this->assigned_to = $this->actor()->can('crm.leads.view') ? $this->actor()->id : null;
            $this->services = [$this->blankService()];

            return;
        }

        $this->authorize('update', $lead);

        $this->lead = $lead;
        $this->fill($lead->only(['location_id', 'lead_source_id', 'referrer_type', 'referrer_id', 'business_line_id', 'lead_level_id', 'lead_priority_id']));

        foreach (['name', 'company_name', 'phone', 'whatsapp', 'office_phone', 'email', 'address', 'referrer_name', 'site_location_text', 'land_area', 'notes'] as $field) {
            $this->{$field} = (string) $lead->getAttribute($field);
        }

        $this->lead_date = $lead->lead_date->toDateString();
        $this->expected_close_date = (string) $lead->expected_close_date?->toDateString();
        $this->floors_planned = (string) $lead->floors_planned;
        $this->expected_value = (string) $lead->expected_value;
        $this->services = $lead->services()->get()->map(fn ($line): array => [
            'service_id' => $line->service_id,
            'estimated_value' => (string) $line->estimated_value,
            'notes' => (string) $line->notes,
        ])->all() ?: [$this->blankService()];
        $this->expected_value_manual = $this->expected_value !== '' && $this->expected_value !== (string) $this->servicesTotal();
    }

    public function updatedPhone(): void
    {
        if ($this->whatsappSameAsPhone) {
            $this->whatsapp = $this->phone;
        }

        $this->checkDuplicates();
    }

    public function updatedWhatsapp(): void
    {
        $this->checkDuplicates();
    }

    public function updatedOfficePhone(): void
    {
        $this->checkDuplicates();
    }

    public function updatedEmail(): void
    {
        $this->checkDuplicates();
    }

    public function updatedWhatsappSameAsPhone(bool $value): void
    {
        if ($value) {
            $this->whatsapp = $this->phone;
            $this->checkDuplicates();
        }
    }

    public function updatedServices(): void
    {
        if (! $this->expected_value_manual) {
            $this->expected_value = (string) $this->servicesTotal();
        }
    }

    public function updatedExpectedValue(): void
    {
        $this->expected_value_manual = true;
    }

    public function updatedLeadSourceId(): void
    {
        if (! $this->requiresReferrer()) {
            $this->reset('referrer_type', 'referrer_id', 'referrer_name', 'referrerSearch');
        }
    }

    public function updatedReferrerType(): void
    {
        $this->reset('referrer_id', 'referrer_name', 'referrerSearch');
    }

    public function pickReferrerCustomer(int $customerId): void
    {
        $customer = Customer::query()->visibleTo($this->actor(), 'crm.customers')->whereNull('merged_into_id')->findOrFail($customerId);

        $this->referrer_id = $customer->id;
        $this->referrer_name = $customer->name;
        $this->referrerSearch = '';
    }

    public function clearReferrer(): void
    {
        $this->reset('referrer_id', 'referrer_name');
    }

    public function addService(): void
    {
        $this->services[] = $this->blankService();
    }

    public function removeService(int $index): void
    {
        if (count($this->services) <= 1) {
            return;
        }

        unset($this->services[$index]);
        $this->services = array_values($this->services);
        $this->updatedServices();
    }

    /**
     * "Add as new enquiry for this customer" (CRM-AC-01): the id must be one of the matches shown.
     */
    public function addAsEnquiry(int $customerId): void
    {
        $match = collect($this->duplicates)->first(fn (array $match): bool => $match['type'] === 'customer' && $match['id'] === $customerId);
        abort_if($match === null, 404);

        $this->lead_source_id = LeadSource::idFor(LeadSource::EXISTING);
        $this->referrer_type = 'customer';
        $this->referrer_id = $customerId;
        $this->referrer_name = $match['name'];
    }

    public function save(CreateLead $createLead, UpdateLead $updateLead): void
    {
        $this->lead === null ? $this->authorize('create', Lead::class) : $this->authorize('update', $this->lead);
        $this->resetErrorBag();

        $input = [
            ...$this->only([
                'lead_date', 'name', 'company_name', 'phone', 'whatsapp', 'office_phone', 'email', 'address', 'location_id',
                'lead_source_id', 'referrer_type', 'referrer_id', 'referrer_name', 'business_line_id', 'lead_level_id', 'lead_priority_id',
                'expected_value', 'expected_value_manual', 'expected_close_date', 'site_location_text', 'land_area', 'floors_planned', 'notes',
                'services', 'assigned_to', 'sales_team_id',
            ]),
            'follow_up' => $this->withFollowUp ? ['activity_type_id' => $this->follow_up_type_id, 'scheduled_at' => $this->follow_up_at] : null,
            'duplicate_reason' => $this->overrideDuplicates && $this->duplicate_reason !== '' ? $this->duplicate_reason : null,
        ];

        try {
            $lead = $this->lead === null
                ? $createLead->handle($this->actor(), $input)
                : $updateLead->handle($this->actor(), $this->lead, $input);
        } catch (ValidationException $exception) {
            if (array_key_exists('duplicates', $exception->errors())) {
                $this->checkDuplicates();
            }

            throw $exception;
        }

        session()->flash('success', $this->lead === null ? __('Lead created.') : __('Lead saved.'));

        $this->redirectRoute('crm.leads.show', $lead, navigate: true);
    }

    private function checkDuplicates(): void
    {
        $this->duplicates = array_map(fn (DuplicateMatch $match): array => [
            'type' => $match->type,
            'id' => $match->id,
            'number' => $match->number,
            'name' => $match->name,
            'status' => $match->status,
            'owner' => $match->owner,
        ], app(FindDuplicates::class)->handle($this->only(['phone', 'whatsapp', 'office_phone', 'email']), ignoreLead: $this->lead));
    }

    private function servicesTotal(): string
    {
        $values = collect($this->services)->pluck('estimated_value')
            ->map(fn (mixed $value): string => str_replace(',', '', trim((string) $value)))
            ->filter(fn (string $value): bool => is_numeric($value));

        if ($values->isEmpty()) {
            return '';
        }

        return (string) $values->reduce(fn (BigDecimal $carry, string $value): BigDecimal => $carry->plus($value), BigDecimal::zero())->toScale(2);
    }

    /**
     * @return array{service_id: null, estimated_value: string, notes: string}
     */
    private function blankService(): array
    {
        return ['service_id' => null, 'estimated_value' => '', 'notes' => ''];
    }

    private function requiresReferrer(): bool
    {
        return (bool) LeadSource::query()->whereKey($this->lead_source_id)->value('requires_referrer');
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(AssignLead $assignLead): View
    {
        $roundRobin = Settings::get('crm.auto_assign_mode') === 'round_robin_team';
        $term = trim($this->referrerSearch);

        return view('livewire.crm.leads.form', [
            'requiresReferrer' => $this->requiresReferrer(),
            'referrerCustomers' => $this->referrer_type === 'customer' && mb_strlen($term) >= 2
                ? Customer::query()->visibleTo($this->actor(), 'crm.customers')->whereNull('merged_into_id')
                    ->where(fn ($query) => $query->where('customer_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                    ->orderBy('name')->limit(8)->get(['id', 'customer_number', 'name', 'phone'])
                : collect(),
            'employees' => $this->referrer_type === 'employee' ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'assignees' => $this->lead === null ? $assignLead->assignableUsers($this->actor()) : collect(),
            'teams' => $roundRobin && $this->lead === null ? SalesTeam::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'serviceOptions' => Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->services)->pluck('service_id')->filter()))->orderBy('name')->get(['id', 'name']),
            'businessLines' => BusinessLine::query()
                ->where(fn ($query) => $query->where('is_active', true)->where('is_internal', false)->orWhere('id', $this->lead?->business_line_id))
                ->ordered()->get(['id', 'name']),
        ])
            ->title($this->lead === null ? __('New lead') : __('Edit lead'))
            ->layoutData(['back' => $this->lead === null ? route('crm.leads.index') : route('crm.leads.show', $this->lead), 'bottomNav' => false]);
    }
}
