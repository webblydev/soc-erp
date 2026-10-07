<?php

namespace App\Modules\Estimation\Livewire\Findings;

use App\Models\User;
use App\Modules\Estimation\Actions\ChangeFindingStatus;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Findings board (docs/05 §5.9, spec §5.12): columns by finding status. Dragging to a closed status
 * asks for the closure note first; a refused move snaps back with a toast.
 */
#[Title('Site findings')]
class Board extends Component
{
    use WithFileUploads;

    private const COLUMN_LIMIT = 50;

    /** @var array<string, string> */
    #[Url(except: [])]
    public array $filters = [];

    /** Status shown on mobile. */
    public string $mobileStatus = FindingStatus::OPEN;

    public ?int $closingFindingId = null;

    public string $closingCode = '';

    public string $closingNote = '';

    /** @var TemporaryUploadedFile|null */
    public $closingPhoto = null;

    public function mount(): void
    {
        $this->authorize('viewAny', SiteInspection::class);
    }

    public function moveFinding(string $findingId, int $position, string $statusId, ChangeFindingStatus $changeStatus): void
    {
        $finding = $this->findingQuery()->find((int) $findingId);
        $target = FindingStatus::query()->find((int) $statusId);

        if ($finding === null || $target === null || $target->id === $finding->finding_status_id) {
            return;
        }

        if ($target->is_closed) {
            $this->closingFindingId = $finding->id;
            $this->closingCode = $target->code;
            $this->reset('closingNote', 'closingPhoto');
            $this->resetErrorBag();
            $this->dispatch('open-sheet-finding-close');

            return;
        }

        $this->change($changeStatus, $finding, $target->code);
    }

    public function close(ChangeFindingStatus $changeStatus): void
    {
        $this->validate(['closingPhoto' => ['nullable', 'image', 'max:10240']]);
        $finding = $this->findingQuery()->findOrFail((int) $this->closingFindingId);

        try {
            $changeStatus->handle($this->actor(), $finding, $this->closingCode, $this->closingNote, $this->closingPhoto);
        } catch (ValidationException $exception) {
            $this->addError('closingNote', (string) collect($exception->errors())->flatten()->first());

            return;
        } catch (AuthorizationException) {
            $this->addError('closingNote', __('You cannot change this finding.'));

            return;
        }

        $this->reset('closingFindingId', 'closingCode', 'closingNote', 'closingPhoto');
        $this->dispatch('close-sheet-finding-close');
        $this->dispatch('toast', type: 'success', description: __('Finding closed.'));
    }

    private function change(ChangeFindingStatus $changeStatus, SiteInspectionFinding $finding, string $code): void
    {
        try {
            $changeStatus->handle($this->actor(), $finding, $code);
            $this->dispatch('toast', type: 'success', description: __('Finding moved.'));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot change this finding.'));
        }
    }

    /**
     * Visible findings of submitted or closed inspections, filtered.
     *
     * @return Builder<SiteInspectionFinding>
     */
    private function findingQuery(): Builder
    {
        $query = SiteInspectionFinding::query()->visibleTo($this->actor())
            ->whereIn('site_inspection_id', SiteInspection::query()->select('id')->where('inspection_status_id', '!=', InspectionStatus::idFor(InspectionStatus::DRAFT)));
        $filter = fn (string $key): string => is_string($this->filters[$key] ?? null) ? $this->filters[$key] : '';

        if ($filter('project') !== '') {
            $query->whereIn('project_id', Project::query()->select('id')->where('project_number', $filter('project')));
        }

        if ($filter('severity') !== '') {
            $query->where('finding_severity_id', (int) $filter('severity'));
        }

        if ($filter('responsible') !== '') {
            $query->where('responsible_type', SiteInspectionFinding::RESPONSIBLE_EMPLOYEE)->where('responsible_id', (int) $filter('responsible'));
        }

        if ($filter('overdue') === '1') {
            $query->overdue();
        }

        return $query;
    }

    public function clearFilters(): void
    {
        $this->filters = [];
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $columns = FindingStatus::query()->ordered()->get()->map(function (FindingStatus $status): array {
            $query = $this->findingQuery()->where('finding_status_id', $status->id);

            return [
                'status' => $status,
                'count' => (clone $query)->count(),
                'findings' => $query->with(['project:id,project_number,name', 'severity:id,name,color', 'inspection:id,inspection_number,contractor_name', 'responsibleEmployee:id,full_name'])
                    ->orderByRaw('due_date IS NULL')->orderBy('due_date')->latest('id')->limit(self::COLUMN_LIMIT)->get(),
            ];
        });
        $visible = SiteInspectionFinding::query()->visibleTo($this->actor());

        return view('livewire.estimation.findings.board', [
            'columns' => $columns,
            'projects' => Project::query()->whereIn('id', (clone $visible)->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'name']),
            'responsibles' => Employee::query()->whereIn('id', (clone $visible)->where('responsible_type', SiteInspectionFinding::RESPONSIBLE_EMPLOYEE)->select('responsible_id'))->orderBy('full_name')->get(['id', 'full_name']),
            'severities' => FindingSeverity::query()->ordered()->get(['id', 'name']),
        ]);
    }
}
