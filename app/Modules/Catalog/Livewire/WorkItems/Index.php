<?php

namespace App\Modules\Catalog\Livewire\WorkItems;

use App\Modules\Catalog\Actions\DeleteCatalogItem;
use App\Modules\Catalog\Models\WorkItem;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Work items')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public function mount(): void
    {
        $this->authorize('catalog.work_items.view');

        $this->filters['active'] ??= '1';
    }

    /**
     * @return Builder<WorkItem>
     */
    protected function listingQuery(): Builder
    {
        return WorkItem::query()->with(['category:id,name,color', 'unit:id,symbol'])->orderBy('code');
    }

    protected function searchColumns(): array
    {
        return ['work_items.code', 'work_items.name'];
    }

    protected function sortColumns(): array
    {
        return ['code' => 'work_items.code', 'name' => 'work_items.name', 'standard_rate' => 'work_items.standard_rate'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->filterString('category') !== '') {
            $query->where('work_item_category_id', (int) $this->filterString('category'));
        }

        if ($this->filterString('unit') !== '') {
            $query->where('unit_id', (int) $this->filterString('unit'));
        }

        if ($this->filterString('active') !== '') {
            $query->where('is_active', $this->filterString('active') === '1');
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('catalog.work_items.export');

        return ListingExport::download('work-items', $this->exportQuery(), [
            'Code' => 'code',
            'Name' => 'name',
            'Category' => 'category.name',
            'Unit' => 'unit.symbol',
            'Formula' => fn (WorkItem $workItem): string => $workItem->measurement_formula->label(),
            'Standard rate' => 'standard_rate',
            'Active' => fn (WorkItem $workItem): string => $workItem->is_active ? 'Yes' : 'No',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('catalog.work_items.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var WorkItem $row */
        app(DeleteCatalogItem::class)->handle($row);
    }

    public function render(): View
    {
        return view('livewire.catalog.work-items.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
