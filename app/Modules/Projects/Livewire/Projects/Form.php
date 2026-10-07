<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Crm\Models\Customer;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Projects\Actions\CreateProject;
use App\Modules\Projects\Actions\UpdateProject;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Services\ServiceLines;
use App\Support\Facades\Settings;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New / edit project (docs/04 §5.2): general, site, people, dates, services grid and, on create,
 * a task template (spec P5, P17).
 */
class Form extends Component
{
    use SavesFromDetailModal;

    /**
     * Plain text fields copied to and from the project.
     */
    private const TEXT_FIELDS = ['name', 'description', 'site_address', 'plot_no', 'land_area', 'built_up_area_sft', 'latitude', 'longitude', 'retention_pct', 'notes'];

    /**
     * Id fields copied to and from the project.
     */
    private const ID_FIELDS = ['customer_id', 'business_line_id', 'project_type_id', 'branch_id', 'location_id', 'land_area_unit_id', 'project_manager_id', 'supervisor_id', 'support_officer_id'];

    public ?Project $project = null;

    #[Url(as: 'customer', except: '')]
    public string $customerNumber = '';

    public string $name = '';

    public int|string|null $customer_id = null;

    public string $customerName = '';

    public string $customerSearch = '';

    public int|string|null $business_line_id = null;

    public int|string|null $project_type_id = null;

    public int|string|null $project_status_id = null;

    public int|string|null $project_phase_id = null;

    public int|string|null $branch_id = null;

    public string $description = '';

    public string $site_address = '';

    public int|string|null $location_id = null;

    public string $latitude = '';

    public string $longitude = '';

    public string $plot_no = '';

    public string $land_area = '';

    public int|string|null $land_area_unit_id = null;

    public string $floors = '';

    public string $basements = '';

    public string $built_up_area_sft = '';

    public int|string|null $project_manager_id = null;

    public int|string|null $supervisor_id = null;

    public int|string|null $support_officer_id = null;

    public string $start_date = '';

    public string $expected_end_date = '';

    public string $handover_date = '';

    public string $retention_pct = '';

    public string $notes = '';

    /** @var list<array<string, mixed>> */
    public array $services = [];

    public bool $applyTemplate = false;

    public int|string|null $task_template_id = null;

    public bool $templateTouched = false;

    public function mount(?Project $project = null): void
    {
        if ($project === null || ! $project->exists) {
            $this->authorize('create', Project::class);

            $this->project_status_id = ProjectStatus::idFor(ProjectStatus::ENQUIRY);
            $this->project_type_id = ProjectType::query()->where('code', 'RESIDENTIAL')->value('id');
            $this->start_date = today()->toDateString();
            $this->project_manager_id = $this->actor()->employee_id;
            $this->services = [$this->blankService()];
            $this->applyTemplate = (bool) Settings::get('projects.auto_apply_task_template', true);

            if ($this->customerNumber !== '') {
                $customer = Customer::query()->visibleTo($this->actor(), 'crm.customers')->where('customer_number', $this->customerNumber)->first();

                if ($customer !== null) {
                    $this->customer_id = $customer->id;
                    $this->customerName = $customer->name;
                    $this->name = $customer->name;
                }
            }

            return;
        }

        $this->authorize('update', $project);

        $this->project = $project;

        foreach (self::ID_FIELDS as $field) {
            $this->{$field} = $project->getAttribute($field);
        }

        foreach (self::TEXT_FIELDS as $field) {
            $this->{$field} = (string) $project->getAttribute($field);
        }

        $this->floors = (string) $project->floors;
        $this->basements = (string) $project->basements;
        $this->customerName = (string) $project->customer?->name;
        $this->project_status_id = $project->project_status_id;
        $this->project_phase_id = $project->project_phase_id;

        foreach (['start_date', 'expected_end_date', 'handover_date'] as $field) {
            $this->{$field} = (string) $project->getAttribute($field)?->toDateString();
        }

        $this->services = array_values($project->services()->get()->map(fn ($line): array => [
            'id' => $line->id,
            'service_id' => $line->service_id,
            'description' => (string) $line->description,
            'quantity' => (string) (float) $line->quantity,
            'unit_id' => $line->unit_id,
            'rate' => (string) (float) $line->rate,
            'discount_amount' => (string) (float) $line->discount_amount,
            'status' => $line->status->name,
            'cancelled' => $line->isCancelled(),
        ])->all());
    }

    public function updatedProjectTypeId(): void
    {
        if ($this->isInternalType()) {
            $this->reset('customer_id', 'customerName', 'customerSearch');
        }
    }

    public function updatedServices(): void
    {
        if (! $this->templateTouched && $this->project === null) {
            $this->task_template_id = $this->defaultTemplateId();
        }
    }

    public function updatedTaskTemplateId(): void
    {
        $this->templateTouched = true;
    }

    public function pickCustomer(int $customerId): void
    {
        $customer = $this->customerOptions(force: true)->firstWhere('id', $customerId);
        abort_if($customer === null, 404);

        $this->customer_id = $customer->id;
        $this->customerName = $customer->name;
        $this->customerSearch = '';

        if ($this->name === '') {
            $this->name = $customer->name;
        }
    }

    public function clearCustomer(): void
    {
        $this->reset('customer_id', 'customerName');
    }

    public function addService(): void
    {
        $this->services = [...$this->services, $this->blankService()];
    }

    public function removeService(int $index): void
    {
        $this->services = array_values(array_filter($this->services, fn (int $key): bool => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function save(CreateProject $createProject, UpdateProject $updateProject): void
    {
        $this->project === null ? $this->authorize('create', Project::class) : $this->authorize('update', $this->project);
        $this->resetErrorBag();

        $input = [
            ...$this->only([...self::TEXT_FIELDS, ...self::ID_FIELDS, 'floors', 'basements', 'start_date', 'expected_end_date', 'handover_date']),
        ];

        if ($this->servicesEditable()) {
            $input['services'] = array_map(fn (array $line): array => collect($line)->only(['id', 'service_id', 'description', 'quantity', 'unit_id', 'rate', 'discount_amount'])->all(), $this->services);
        }

        if ($this->project === null) {
            $project = $createProject->handle($this->actor(), [
                ...$input,
                'project_status_id' => $this->project_status_id,
                'project_phase_id' => $this->project_phase_id ?: null,
                'task_template_id' => $this->applyTemplate ? $this->task_template_id : null,
            ]);

            $this->redirectAfterSave(__('Project :number created.', ['number' => $project->project_number]), 'projects.projects.show', $project);

            return;
        }

        $project = $updateProject->handle($this->actor(), $this->project, $input);

        $this->redirectAfterSave(__('Project saved.'), 'projects.projects.show', $project);
    }

    /**
     * Services can be edited on create and while the contract is unsigned (PRJ-BR-04).
     */
    public function servicesEditable(): bool
    {
        return $this->project === null || ! $this->project->hasSignedContract();
    }

    /**
     * Live line amounts and the contract value for the grid footer.
     *
     * @return array{lines: list<string|null>, total: string}
     */
    private function totals(): array
    {
        $total = BigDecimal::zero();
        $lines = [];

        foreach ($this->services as $line) {
            $quantity = str_replace(',', '', trim((string) ($line['quantity'] ?? '')));
            $rate = str_replace(',', '', trim((string) ($line['rate'] ?? '')));
            $discount = str_replace(',', '', trim((string) ($line['discount_amount'] ?? ''))) ?: '0';

            if (! is_numeric($quantity) || ! is_numeric($rate) || ! is_numeric($discount)) {
                $lines[] = null;

                continue;
            }

            $amount = ServiceLines::amount($quantity, $rate, $discount);
            $lines[] = $amount;

            if (! ($line['cancelled'] ?? false)) {
                $total = $total->plus($amount);
            }
        }

        return ['lines' => $lines, 'total' => (string) $total->toScale(2)];
    }

    private function defaultTemplateId(): ?int
    {
        $serviceId = collect($this->services)->pluck('service_id')->filter()->first();
        $templates = TaskTemplate::query()->where('is_active', true);

        return ($serviceId !== null ? (clone $templates)->where('service_id', (int) $serviceId)->orderBy('id')->value('id') : null)
            ?? ($this->project_type_id !== null ? (clone $templates)->where('project_type_id', (int) $this->project_type_id)->orderBy('id')->value('id') : null);
    }

    private function isInternalType(): bool
    {
        return (bool) ProjectType::query()->whereKey($this->project_type_id)->value('is_internal');
    }

    /**
     * Visible, active customers that are not blocked or merged, matching the search.
     *
     * @return Collection<int, Customer>
     */
    private function customerOptions(bool $force = false): Collection
    {
        $term = trim($this->customerSearch);

        if (! $force && mb_strlen($term) < 2) {
            return collect();
        }

        return Customer::query()->visibleTo($this->actor(), 'crm.customers')->whereNull('merged_into_id')
            ->whereHas('status', fn ($query) => $query->where('is_blocked', false))
            ->when(mb_strlen($term) >= 2, fn ($query) => $query->where(fn ($query) => $query->where('customer_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('name')->limit($force ? 200 : 8)->get(['id', 'customer_number', 'name', 'phone']);
    }

    /**
     * @return array{id: null, service_id: null, description: string, quantity: string, unit_id: null, rate: string, discount_amount: string}
     */
    private function blankService(): array
    {
        return ['id' => null, 'service_id' => null, 'description' => '', 'quantity' => '1', 'unit_id' => null, 'rate' => '', 'discount_amount' => ''];
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $serviceIds = collect($this->services)->pluck('service_id')->filter()->all();

        return view('livewire.projects.projects.form', [
            'isInternal' => $this->isInternalType(),
            'servicesEditable' => $this->servicesEditable(),
            'totals' => $this->totals(),
            'customerOptions' => $this->customerOptions(),
            'canCreateCustomer' => $this->actor()->can('crm.customers.create'),
            'businessLines' => BusinessLine::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->business_line_id))->ordered()->get(['id', 'name', 'project_prefix']),
            'serviceOptions' => Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $serviceIds))->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', collect($this->services)->pluck('unit_id')->filter()))->ordered()->get(['id', 'name', 'symbol']),
            'openStatuses' => ProjectStatus::query()->active()->where('is_closed', false)->ordered()->get(['id', 'name']),
            'templates' => TaskTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])
            ->title($this->project === null ? __('New project') : __('Edit project'))
            ->layoutData(['back' => $this->project === null ? route('projects.projects.index') : route('projects.projects.show', $this->project), 'bottomNav' => false]);
    }
}
