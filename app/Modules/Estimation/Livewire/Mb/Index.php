<?php

namespace App\Modules\Estimation\Livewire\Mb;

use App\Models\User;
use App\Modules\Estimation\Actions\RejectMeasurements;
use App\Modules\Estimation\Actions\VerifyMeasurements;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Measurement Book (docs/05 §5.5, spec §5.6): presets, filters, bulk verify / reject and export.
 */
#[Title('Measurement book')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public const PRESETS = ['awaiting', 'mine', 'verified', 'rejected'];

    #[Url(as: 'preset', except: '')]
    public string $preset = '';

    public bool $selecting = false;

    public string $rejectReason = '';

    private const PROJECT_NUMBER = '(select project_number from projects where projects.id = measurement_entries.project_id)';

    public function mount(): void
    {
        $this->authorize('viewAny', MeasurementEntry::class);

        if (is_string(request()->query('project'))) {
            $this->filters['project'] = request()->query('project');
        }

        if ($this->preset !== '') {
            $this->applyPreset($this->preset);
        }
    }

    public function applyPreset(string $preset): void
    {
        if (! in_array($preset, self::PRESETS, true)) {
            return;
        }

        $this->preset = $preset;
        $this->filters = match ($preset) {
            'awaiting' => ['status' => (string) MbStatus::idFor(MbStatus::RECORDED)],
            'mine' => ['measured_by' => (string) $this->actor()->employee_id],
            'verified' => ['status' => (string) MbStatus::idFor(MbStatus::VERIFIED)],
            'rejected' => ['status' => (string) MbStatus::idFor(MbStatus::REJECTED)],
        };
        $this->updatedFilters();
        $this->selected = [];
    }

    /**
     * @return Builder<MeasurementEntry>
     */
    protected function listingQuery(): Builder
    {
        return MeasurementEntry::query()
            ->visibleTo($this->actor())
            ->with(['project:id,project_number,name,project_manager_id', 'status:id,name,color,code', 'unit:id,symbol', 'measurer:id,full_name', 'estimateLine:id,line_no'])
            ->latest('measurement_entries.measured_on')
            ->orderByDesc('measurement_entries.id');
    }

    protected function searchColumns(): array
    {
        return ['measurement_entries.mb_number', 'measurement_entries.description', 'measurement_entries.location', self::PROJECT_NUMBER];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'measurement_entries.mb_number', 'measured' => 'measurement_entries.measured_on', 'amount' => 'measurement_entries.amount'];
    }

    /**
     * @param  Builder<MeasurementEntry>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        foreach (['status' => 'mb_status_id', 'measured_by' => 'measured_by'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('measurement_entries.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('project') !== '') {
            $query->whereIn('measurement_entries.project_id', Project::query()->select('id')->where('project_number', $this->filterString('project')));
        }

        if (($from = $this->validDate($this->filters['from'] ?? null)) !== null) {
            $query->whereDate('measurement_entries.measured_on', '>=', $from);
        }

        if (($to = $this->validDate($this->filters['to'] ?? null)) !== null) {
            $query->whereDate('measurement_entries.measured_on', '<=', $to);
        }
    }

    public function verifySelected(VerifyMeasurements $verify): void
    {
        $this->bulk(fn (array $ids): int => $verify->handle($this->actor(), $ids), ':count entries verified.');
    }

    public function rejectSelected(RejectMeasurements $reject): void
    {
        $this->bulk(fn (array $ids): int => $reject->handle($this->actor(), $ids, $this->rejectReason), ':count entries rejected.');
        $this->dispatch('close-sheet-mb-reject');
        $this->reset('rejectReason');
    }

    /**
     * @param  \Closure(list<int>): int  $action
     */
    private function bulk(\Closure $action, string $message): void
    {
        $ids = array_values(array_map('intval', $this->filteredQuery()->whereKey($this->selectedIds())->pluck('measurement_entries.id')->all()));

        if ($ids === []) {
            return;
        }

        try {
            $count = $action($ids);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->selected = [];
        $this->selecting = false;
        $this->dispatch('toast', type: 'success', description: trans_choice($message, $count, ['count' => $count]));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', MeasurementEntry::class);

        return ListingExport::download('measurement-book', $this->exportQuery(), [
            'MB #' => 'mb_number', 'Project' => 'project.project_number', 'MB book' => 'mb_book_no', 'MB page' => 'mb_page_no',
            'Measured on' => 'measured_on', 'Measured by' => 'measurer.full_name', 'BOQ line' => 'estimateLine.line_no',
            'Description' => 'description', 'Location' => 'location', 'Qty' => 'quantity', 'Unit' => 'unit.symbol', 'Rate' => 'rate',
            'Amount' => 'amount', 'Achievement %' => 'achievement_pct', 'Status' => 'status.name',
        ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $visible = MeasurementEntry::query()->visibleTo($this->actor());

        return view('livewire.estimation.mb.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'statuses' => MbStatus::query()->ordered()->get(['id', 'name']),
            'projects' => Project::query()->whereIn('id', (clone $visible)->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'name']),
            'measurers' => Employee::query()->whereIn('id', (clone $visible)->select('measured_by'))->orderBy('full_name')->get(['id', 'full_name']),
            'canVerify' => $this->actor()->can('site.mb.verify'),
        ]);
    }
}
