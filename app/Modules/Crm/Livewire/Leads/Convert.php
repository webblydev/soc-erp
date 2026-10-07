<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\ConvertLead;
use App\Modules\Crm\Actions\FindDuplicates;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\TaskTemplate;
use App\Support\Facades\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Lead conversion wizard (docs/03 §5.7, Projects spec P21): 1 Customer, 2 Project, 3 Review &
 * confirm. The project step can be skipped only when crm.allow_convert_without_project is on.
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

    /** @var array<string, mixed> */
    public array $project = [];

    public bool $skipProject = false;

    public bool $applyTemplate = false;

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

        $this->project = [
            'business_line_id' => $lead->business_line_id,
            'project_type_id' => ProjectType::query()->where('code', 'RESIDENTIAL')->value('id'),
            'name' => '',
            'site_address' => (string) ($lead->site_location_text ?? $lead->address),
            'location_id' => $lead->location_id,
            'start_date' => today()->toDateString(),
            'project_manager_id' => null,
            'task_template_id' => null,
            'notes' => '',
            'services' => $lead->services->map(fn ($line): array => [
                'service_id' => $line->service_id,
                'quantity' => '1',
                'rate' => $line->estimated_value !== null ? rtrim(rtrim((string) $line->estimated_value, '0'), '.') : '',
                'description' => (string) $line->notes,
            ])->values()->all(),
        ];
        $this->applyTemplate = (bool) Settings::get('projects.auto_apply_task_template', true);
        $this->project['task_template_id'] = $this->defaultTemplateId();
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

        if ($this->step === 2) {
            if (! $this->skipProject) {
                $this->validate([
                    'project.business_line_id' => ['required'],
                    'project.project_type_id' => ['required'],
                    'project.name' => ['required', 'string', 'max:255'],
                    'project.project_manager_id' => ['required'],
                ], [], [
                    'project.business_line_id' => __('business line'),
                    'project.project_type_id' => __('project type'),
                    'project.name' => __('project name'),
                    'project.project_manager_id' => __('project manager'),
                ]);
            }

            $this->step = 3;

            return;
        }

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

        if ($this->project['name'] === '') {
            $customerName = $this->choice === 'link' ? (string) Customer::query()->whereKey($this->customerId)->value('name') : (string) $this->customer['name'];
            $this->project['name'] = collect([$customerName, $this->lead->site_location_text])->filter()->implode(', ');
        }

        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function addProjectService(): void
    {
        $this->project['services'][] = ['service_id' => null, 'quantity' => '1', 'rate' => '', 'description' => ''];
    }

    public function removeProjectService(int $index): void
    {
        unset($this->project['services'][$index]);
        $this->project['services'] = array_values($this->project['services']);
    }

    public function convert(ConvertLead $convertLead): void
    {
        $this->authorize('convert', $this->lead);
        $this->resetErrorBag();

        $input = $this->choice === 'link'
            ? ['customer_id' => $this->customerId]
            : ['customer' => [...$this->customer, 'duplicate_reason' => $this->duplicate_reason !== '' ? $this->duplicate_reason : null]];
        $input['skip_project'] = $this->skipProject && $this->allowsSkip();
        $input['project'] = [...$this->project, 'task_template_id' => $this->applyTemplate ? $this->project['task_template_id'] : null];

        try {
            $customer = $convertLead->handle($this->actor(), $this->lead, $input);
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [
                ! str_starts_with($key, 'project') && in_array(strtok($key, '.'), [...self::CUSTOMER_FIELDS, 'contacts'], true) ? "customer.{$key}" : $key => $messages,
            ]);

            if ($errors->keys()->contains(fn (string $key): bool => str_starts_with($key, 'customer') || $key === 'duplicate_reason')) {
                $this->step = 1;
            } elseif ($errors->keys()->contains(fn (string $key): bool => str_starts_with($key, 'project'))) {
                $this->step = 2;
            }

            throw ValidationException::withMessages($errors->all());
        }

        $project = $this->lead->fresh()?->convertedProject;

        session()->flash('success', $project !== null
            ? __('Lead converted to :customer and project :project.', ['customer' => $customer->customer_number, 'project' => $project->project_number])
            : __('Lead converted to :number.', ['number' => $customer->customer_number]));

        match (true) {
            $project !== null && $this->actor()->can('view', $project) => $this->redirectRoute('projects.projects.show', $project, navigate: true),
            $this->actor()->can('view', $customer) => $this->redirectRoute('crm.customers.show', $customer, navigate: true),
            default => $this->redirectRoute('crm.leads.show', $this->lead, navigate: true),
        };
    }

    private function allowsSkip(): bool
    {
        return (bool) Settings::get('crm.allow_convert_without_project', false);
    }

    private function defaultTemplateId(): ?int
    {
        $serviceId = $this->lead->services->first()?->service_id;

        return $serviceId !== null ? TaskTemplate::query()->where('is_active', true)->where('service_id', $serviceId)->orderBy('id')->value('id') : null;
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
            'allowsSkip' => $this->allowsSkip(),
            'projectLines' => BusinessLine::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->project['business_line_id'] ?? null))->ordered()->get(['id', 'name', 'project_prefix']),
            'serviceOptions' => Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->project['services'] ?? [])->pluck('service_id')->filter()))->orderBy('name')->get(['id', 'name']),
            'templates' => TaskTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])
            ->title(__('Convert :number', ['number' => $this->lead->lead_number]))
            ->layoutData(['back' => route('crm.leads.show', $this->lead), 'bottomNav' => false]);
    }
}
