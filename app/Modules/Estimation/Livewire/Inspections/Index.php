<?php

namespace App\Modules\Estimation\Livewire\Inspections;

use App\Models\User;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Projects\Models\Project;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Site inspections list (docs/05 §5.7, spec §5.9) with the v1 Project Visit columns.
 */
#[Title('Site inspections')]
class Index extends Component
{
    use WithListing;

    private const PROJECT_NUMBER = '(select project_number from projects where projects.id = site_inspections.project_id)';

    public function mount(): void
    {
        $this->authorize('viewAny', SiteInspection::class);

        if (is_string(request()->query('project'))) {
            $this->filters['project'] = request()->query('project');
        }
    }

    /**
     * @return Builder<SiteInspection>
     */
    protected function listingQuery(): Builder
    {
        return SiteInspection::query()
            ->visibleTo($this->actor())
            ->with(['project:id,project_number,name', 'type:id,name', 'status:id,name,color', 'engineer:id,full_name'])
            ->withCount(['findings', 'findings as open_findings_count' => fn ($query) => $query->open()])
            ->latest('site_inspections.inspection_date')
            ->orderByDesc('site_inspections.id');
    }

    protected function searchColumns(): array
    {
        return ['site_inspections.inspection_number', 'site_inspections.contractor_name', 'site_inspections.permittee_name', 'site_inspections.project_engineer_name', self::PROJECT_NUMBER];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'site_inspections.inspection_number', 'date' => 'site_inspections.inspection_date'];
    }

    /**
     * @param  Builder<SiteInspection>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        foreach (['type' => 'inspection_type_id', 'status' => 'inspection_status_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('site_inspections.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('project') !== '') {
            $query->whereIn('site_inspections.project_id', Project::query()->select('id')->where('project_number', $this->filterString('project')));
        }

        if ($this->filterString('open') === '1') {
            $query->whereIn('site_inspections.id', SiteInspectionFinding::query()->open()->select('site_inspection_id'));
        }

        if (($from = $this->validDate($this->filters['from'] ?? null)) !== null) {
            $query->whereDate('site_inspections.inspection_date', '>=', $from);
        }

        if (($to = $this->validDate($this->filters['to'] ?? null)) !== null) {
            $query->whereDate('site_inspections.inspection_date', '<=', $to);
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', SiteInspection::class);

        return ListingExport::download('site-inspections', $this->filteredQuery(), [
            'Inspection #' => 'inspection_number', 'Date' => 'inspection_date', 'Project' => 'project.project_number',
            'Project engineer' => fn (SiteInspection $inspection) => $inspection->engineerName(), 'Contractor' => 'contractor_name',
            'Permittee' => 'permittee_name', 'Type' => 'type.name', 'Open findings' => 'open_findings_count', 'Findings' => 'findings_count',
            'Status' => 'status.name',
        ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.estimation.inspections.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'types' => InspectionType::query()->ordered()->get(['id', 'name']),
            'statuses' => InspectionStatus::query()->ordered()->get(['id', 'name']),
            'projects' => Project::query()->whereIn('id', SiteInspection::query()->visibleTo($this->actor())->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'name']),
        ]);
    }
}
