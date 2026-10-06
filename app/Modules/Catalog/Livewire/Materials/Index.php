<?php

namespace App\Modules\Catalog\Livewire\Materials;

use App\Modules\Catalog\Models\Material;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Materials')]
class Index extends Component
{
    use WithListing;

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

    public function render(): View
    {
        return view('livewire.catalog.materials.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
