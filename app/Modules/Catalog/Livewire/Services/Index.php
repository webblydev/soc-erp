<?php

namespace App\Modules\Catalog\Livewire\Services;

use App\Modules\Catalog\Actions\DeleteCatalogItem;
use App\Modules\Catalog\Models\Service;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Services')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public function mount(): void
    {
        $this->authorize('catalog.services.view');

        $this->filters['active'] ??= '1';
    }

    /**
     * @return Builder<Service>
     */
    protected function listingQuery(): Builder
    {
        return Service::query()
            ->with(['category:id,name,color', 'businessLine:id,code,name', 'pricingBasis:id,name', 'defaultUnit:id,symbol'])
            ->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['services.code', 'services.name'];
    }

    protected function sortColumns(): array
    {
        return ['code' => 'services.code', 'name' => 'services.name', 'default_rate' => 'services.default_rate'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->filterString('category') !== '') {
            $query->where('service_category_id', (int) $this->filterString('category'));
        }

        if ($this->filterString('business_line') !== '') {
            $query->where('business_line_id', (int) $this->filterString('business_line'));
        }

        if ($this->filterString('active') !== '') {
            $query->where('is_active', $this->filterString('active') === '1');
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('catalog.services.export');

        return ListingExport::download('services', $this->exportQuery(), [
            'Code' => 'code',
            'Name' => 'name',
            'Category' => 'category.name',
            'Business line' => 'businessLine.name',
            'Pricing' => 'pricingBasis.name',
            'Default rate' => 'default_rate',
            'Active' => fn (Service $service): string => $service->is_active ? 'Yes' : 'No',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('catalog.services.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Service $row */
        app(DeleteCatalogItem::class)->handle($row);
    }

    public function render(): View
    {
        return view('livewire.catalog.services.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
