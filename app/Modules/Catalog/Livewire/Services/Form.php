<?php

namespace App\Modules\Catalog\Livewire\Services;

use App\Modules\Catalog\Actions\SaveService;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    public ?Service $service = null;

    public string $code = '';

    public string $name = '';

    public int|string|null $service_category_id = null;

    public int|string|null $business_line_id = null;

    public int|string|null $default_unit_id = null;

    public int|string|null $pricing_basis_id = null;

    public string $default_rate = '';

    public bool $requires_approval_tracking = false;

    public string $description = '';

    public bool $is_active = true;

    public function mount(?Service $service = null): void
    {
        if ($service === null || ! $service->exists) {
            $this->authorize('catalog.services.create');
            $this->pricing_basis_id = PricingBasis::query()->where('code', PricingBasis::FIXED)->value('id');

            return;
        }

        $this->authorize('catalog.services.update');

        $this->service = $service;
        $this->fill($service->only(['code', 'name', 'service_category_id', 'business_line_id', 'default_unit_id', 'pricing_basis_id', 'requires_approval_tracking', 'is_active']));
        $this->default_rate = $service->default_rate !== null ? rtrim(rtrim($service->default_rate, '0'), '.') : '';
        $this->description = (string) $service->description;
    }

    public function save(SaveService $saveService): void
    {
        $this->authorize($this->service === null ? 'catalog.services.create' : 'catalog.services.update');

        $saveService->handle($this->only([
            'code', 'name', 'service_category_id', 'business_line_id', 'default_unit_id', 'pricing_basis_id',
            'default_rate', 'requires_approval_tracking', 'description', 'is_active',
        ]), $this->service);

        session()->flash('success', $this->service === null ? __('Service created.') : __('Service saved.'));

        $this->redirectRoute('catalog.services.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.catalog.services.form', [
            'businessLines' => BusinessLine::query()
                ->where(fn ($query) => $query->where('is_active', true)->where('is_internal', false)
                    ->orWhere('id', $this->service?->business_line_id))
                ->ordered()->get(['id', 'name']),
        ])
            ->title($this->service === null ? __('New service') : __('Edit service'))
            ->layoutData(['back' => route('catalog.services.index'), 'bottomNav' => false]);
    }
}
