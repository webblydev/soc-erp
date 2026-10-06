<?php

namespace App\Modules\Crm\Livewire\Teams;

use App\Modules\Crm\Models\SalesTeam;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sales teams list (docs/03 §5.8): members, open leads and leads won this month per team.
 */
#[Title('Sales teams')]
class Index extends Component
{
    use WithListing;

    public function mount(): void
    {
        $this->authorize('crm.teams.view');

        $this->filters['active'] ??= '1';
    }

    /**
     * @return Builder<SalesTeam>
     */
    protected function listingQuery(): Builder
    {
        return SalesTeam::query()
            ->with(['manager:id,name', 'businessLine:id,name'])
            ->withCount([
                'activeMembers',
                'leads as open_leads_count' => fn ($query) => $query->open(),
                'leads as won_this_month_count' => fn ($query) => $query->whereBetween('won_at', [now()->startOfMonth(), now()->endOfMonth()]),
            ])
            ->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['sales_teams.name'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'sales_teams.name', 'monthly_target_amount' => 'sales_teams.monthly_target_amount'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->filterString('active') !== '') {
            $query->where('is_active', $this->filterString('active') === '1');
        }
    }

    public function render(): View
    {
        return view('livewire.crm.teams.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
