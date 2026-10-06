<?php

namespace App\Modules\Catalog\Livewire\Materials;

use App\Modules\Catalog\Actions\DeleteCatalogItem;
use App\Modules\Catalog\Models\Material;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Materials')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public function mount(): void
    {
        $this->authorize('catalog.materials.view');

        $this->filters['active'] ??= '1';
    }

    /**
     * @return Builder<Material>
     */
    protected function listingQuery(): Builder
    {
        return Material::query()->with(['category:id,name,color', 'unit:id,symbol'])->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['materials.code', 'materials.name'];
    }

    protected function sortColumns(): array
    {
        return ['code' => 'materials.code', 'name' => 'materials.name', 'standard_rate' => 'materials.standard_rate'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->filterString('category') !== '') {
            $query->where('material_category_id', (int) $this->filterString('category'));
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
        $this->authorize('catalog.materials.export');

        return ListingExport::download('materials', $this->exportQuery(), [
            'Code' => 'code',
            'Name' => 'name',
            'Category' => 'category.name',
            'Unit' => 'unit.symbol',
            'Standard rate' => 'standard_rate',
            'Active' => fn (Material $material): string => $material->is_active ? 'Yes' : 'No',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('catalog.materials.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Material $row */
        app(DeleteCatalogItem::class)->handle($row);
    }

    public function render(): View
    {
        return view('livewire.catalog.materials.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
