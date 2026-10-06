<?php

namespace App\Support\Listing;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Search, filters, sort and paging for list screens (docs/00 §7.2, §7.6).
 * Desktop renders paginatedRows(); mobile renders mobileRows() with infinite scroll.
 * Sort keys are whitelisted through sortColumns(), so URL tampering cannot reach other columns.
 */
trait WithListing
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public const MAX_MOBILE_ROWS = 500;

    #[Url(except: '')]
    public string $search = '';

    /** @var array<string, string> */
    #[Url(except: [])]
    public array $filters = [];

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    public int $limit = 25;

    public bool $hasMoreRows = false;

    /**
     * The base query, including eager loads and the default order.
     *
     * @return Builder<covariant Model>
     */
    abstract protected function listingQuery(): Builder;

    /**
     * Columns matched by the search box.
     *
     * @return list<string>
     */
    abstract protected function searchColumns(): array;

    /**
     * Sortable keys mapped to their columns.
     *
     * @return array<string, string>
     */
    abstract protected function sortColumns(): array;

    /**
     * Apply $this->filters to the query. Override in the component.
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function applyFilters(Builder $query): void {}

    public function updatedSearch(): void
    {
        $this->resetListing();
    }

    public function updatedFilters(): void
    {
        $this->resetListing();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $key): void
    {
        if (! array_key_exists($key, $this->sortColumns())) {
            return;
        }

        $this->direction = $this->sort === $key && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $key;
        $this->resetListing();
    }

    public function loadMore(): void
    {
        $this->limit = min(self::MAX_MOBILE_ROWS, max(25, intdiv($this->limit, 25) * 25) + 25);
    }

    public function clearFilters(): void
    {
        $this->reset('filters', 'search');
        $this->resetListing();
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginatedRows(): LengthAwarePaginator
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        return $this->filteredQuery()->paginate($this->perPage);
    }

    /**
     * The first $limit rows for the mobile list; sets $hasMoreRows.
     *
     * @return Collection<int, Model>
     */
    public function mobileRows(): Collection
    {
        $this->limit = min(self::MAX_MOBILE_ROWS, max(25, intdiv($this->limit, 25) * 25));

        $rows = $this->filteredQuery()->limit($this->limit + 1)->get();
        $this->hasMoreRows = $rows->count() > $this->limit;

        return $rows->take($this->limit);
    }

    /**
     * @return Builder<covariant Model>
     */
    protected function filteredQuery(): Builder
    {
        $query = $this->listingQuery();
        $this->applyFilters($query);

        $term = trim($this->search);

        if ($term !== '' && $this->searchColumns() !== []) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';

            $query->where(function (Builder $query) use ($like): void {
                foreach ($this->searchColumns() as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        $column = $this->sortColumns()[$this->sort] ?? null;

        if ($column !== null) {
            $query->reorder($column, $this->direction === 'desc' ? 'desc' : 'asc');
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        return $query;
    }

    private function resetListing(): void
    {
        $this->limit = 25;
        $this->resetPage();
    }

    /**
     * A filter value as a string; anything else (e.g. a tampered array) counts as empty.
     */
    protected function filterString(string $key): string
    {
        $value = $this->filters[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * A filter value as a real Y-m-d date, or null when it is empty or not a valid date.
     */
    protected function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
