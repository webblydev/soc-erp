<?php

namespace App\Modules\Crm\Livewire\Teams;

use App\Modules\Crm\Actions\DeleteSalesTeam;
use App\Modules\Crm\Models\SalesTeam;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sales teams list (docs/03 §5.8): members, open leads and leads won this month per team.
 */
#[Title('Sales teams')]
class Index extends Component
{
    use WithBulkActions, WithListing;

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

    public function export(): BinaryFileResponse
    {
        $this->authorize('crm.teams.view');

        return ListingExport::download('sales-teams', $this->exportQuery(), [
            'Team' => 'name',
            'Manager' => 'manager.name',
            'Members' => 'active_members_count',
            'Business line' => 'businessLine.name',
            'Monthly target' => 'monthly_target_amount',
            'Open leads' => 'open_leads_count',
            'Won this month' => 'won_this_month_count',
            'Active' => fn (SalesTeam $team): string => $team->is_active ? 'Yes' : 'No',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('crm.teams.manage');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var SalesTeam $row */
        app(DeleteSalesTeam::class)->handle($row);
    }

    public function render(): View
    {
        return view('livewire.crm.teams.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
