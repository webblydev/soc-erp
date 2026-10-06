<?php

namespace App\Modules\Catalog\Livewire\WorkItems;

use App\Modules\Catalog\Models\WorkItem;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Work items')]
class Index extends Component
{
    use WithListing;

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

    public function render(): View
    {
        return view('livewire.catalog.work-items.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
