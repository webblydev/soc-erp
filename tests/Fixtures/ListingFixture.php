<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\Listing\WithListing;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ListingFixture extends Component
{
    use WithListing;

    /**
     * @return Builder<User>
     */
    protected function listingQuery(): Builder
    {
        return User::query()->orderBy('id');
    }

    protected function searchColumns(): array
    {
        return ['name', 'username'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'name'];
    }

    protected function applyFilters(Builder $query): void
    {
        if (($this->filters['active'] ?? '') !== '') {
            $query->where('is_active', $this->filters['active'] === '1');
        }
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <p>desktop:{{ $this->paginatedRows()->map(fn ($row): string => $row->username.',')->implode('') }}</p>
                <p>mobile:{{ $this->mobileRows()->map(fn ($row): string => $row->username.',')->implode('') }}</p>
                <p>more:{{ $this->hasMoreRows ? 'yes' : 'no' }}</p>
            </div>
            BLADE;
    }
}
