<?php

namespace App\Modules\Estimation\Livewire\Estimates;

use App\Models\User;
use App\Modules\Estimation\Actions\DeleteEstimate;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Projects\Models\Project;
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
 * Estimates list (docs/05 §5.1, spec §5.1): selection, row actions and export. Only the latest
 * revision of each estimate shows unless "All revisions" is chosen.
 */
#[Title('Estimates')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    private const PROJECT_NUMBER = '(select project_number from projects where projects.id = estimates.project_id)';

    public function mount(): void
    {
        $this->authorize('viewAny', Estimate::class);

        if (is_string(request()->query('project'))) {
            $this->filters['project'] = request()->query('project');
        }
    }

    /**
     * @return Builder<Estimate>
     */
    protected function listingQuery(): Builder
    {
        return Estimate::query()
            ->visibleTo($this->actor())
            ->with(['kind:id,name,code', 'status:id,name,color,code', 'project:id,project_number,name', 'preparer:id,full_name'])
            ->latest('estimates.estimate_date')
            ->orderByDesc('estimates.id');
    }

    protected function searchColumns(): array
    {
        return ['estimates.estimate_number', 'estimates.title', self::PROJECT_NUMBER];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'estimates.estimate_number', 'date' => 'estimates.estimate_date', 'total' => 'estimates.total_amount'];
    }

    /**
     * @param  Builder<Estimate>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if ($this->filterString('revisions') !== 'all') {
            $query->latestRevisions();
        }

        foreach (['kind' => 'estimate_kind_id', 'status' => 'estimate_status_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('estimates.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('project') !== '') {
            $query->whereIn('estimates.project_id', Project::query()->select('id')->where('project_number', $this->filterString('project')));
        }

        if (($from = $this->validDate($this->filters['from'] ?? null)) !== null) {
            $query->whereDate('estimates.estimate_date', '>=', $from);
        }

        if (($to = $this->validDate($this->filters['to'] ?? null)) !== null) {
            $query->whereDate('estimates.estimate_date', '<=', $to);
        }
    }

    public function export(): BinaryFileResponse
    {
        abort_unless($this->actor()->can('estimation.estimates.export'), 403);

        return ListingExport::download('estimates', $this->exportQuery(), [
            'Estimate #' => 'estimate_number', 'Kind' => 'kind.name', 'Project' => 'project.project_number', 'Title' => 'title',
            'Date' => 'estimate_date', 'Revision' => 'revision_no', 'Status' => 'status.name', 'Prepared by' => 'preparer.full_name',
            'Subtotal' => 'subtotal', 'Total' => 'total_amount',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('estimation.estimates.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Estimate $row */
        app(DeleteEstimate::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.estimation.estimates.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'kinds' => EstimateKind::query()->ordered()->get(['id', 'name']),
            'statuses' => EstimateStatus::query()->ordered()->get(['id', 'name']),
            'projects' => Project::query()->whereIn('id', Estimate::query()->visibleTo($this->actor())->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'name']),
        ]);
    }
}
