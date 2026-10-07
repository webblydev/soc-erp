<?php

namespace App\Modules\Projects\Livewire\Approvals;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ProjectApproval;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Approvals tracker across projects (docs/04 §5.9). Pending approvals show by default.
 */
#[Title('Approvals')]
class Index extends Component
{
    use WithListing;

    public function mount(): void
    {
        $this->authorize('viewAny', ProjectApproval::class);
    }

    /**
     * @return Builder<ProjectApproval>
     */
    protected function listingQuery(): Builder
    {
        return ProjectApproval::query()
            ->visibleTo($this->actor())
            ->with(['project:id,project_number,name', 'authority:id,name', 'type:id,name,typical_days', 'status:id,name,color,is_final', 'responsible:id,full_name'])
            ->orderByRaw('expected_on IS NULL')
            ->orderBy('project_approvals.expected_on')
            ->orderByDesc('project_approvals.id');
    }

    protected function searchColumns(): array
    {
        return ['project_approvals.reference_no'];
    }

    protected function sortColumns(): array
    {
        return ['submitted' => 'project_approvals.submitted_on', 'expected' => 'project_approvals.expected_on'];
    }

    /**
     * @param  Builder<ProjectApproval>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        match ($this->filterString('state')) {
            'all' => null,
            'final' => $query->whereIn('project_approvals.approval_status_id', ApprovalStatus::query()->select('id')->where('is_final', true)),
            'overdue' => $query->overdue(),
            default => $query->pending(),
        };

        foreach (['authority' => 'approval_authority_id', 'type' => 'approval_type_id', 'status' => 'approval_status_id', 'responsible' => 'responsible_employee_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('project_approvals.'.$column, (int) $this->filterString($key));
            }
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', ProjectApproval::class);

        return ListingExport::download('approvals', $this->filteredQuery(), [
            'Project' => 'project.project_number', 'Authority' => 'authority.name', 'Type' => 'type.name', 'Reference' => 'reference_no',
            'Responsible' => 'responsible.full_name', 'Status' => 'status.name', 'Submitted' => 'submitted_on', 'Expected' => 'expected_on', 'Approved' => 'approved_on',
        ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.projects.approvals.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'responsibles' => Employee::query()->whereIn('id', ProjectApproval::query()->visibleTo($this->actor())->whereNotNull('responsible_employee_id')->select('responsible_employee_id'))->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }
}
