<?php

namespace App\Modules\Estimation\Livewire\Inspections;

use App\Models\User;
use App\Modules\Estimation\Actions\AddFinding;
use App\Modules\Estimation\Actions\ChangeFindingStatus;
use App\Modules\Estimation\Actions\CloseInspection;
use App\Modules\Estimation\Actions\DeleteInspection;
use App\Modules\Estimation\Actions\SubmitInspection;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Foundation\Models\DocumentType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Site inspection detail (docs/05 §5.8, spec §5.11): key figures, finding cards with photos and
 * status changes, Add finding, Submit, Close and Print.
 */
class Show extends Component
{
    use WithFileUploads;

    public SiteInspection $inspection;

    public ?int $statusFindingId = null;

    public string $statusCode = '';

    public string $statusNote = '';

    /** @var TemporaryUploadedFile|null */
    public $statusPhoto = null;

    public ?int $photoFindingId = null;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    /** @var array<string, mixed> */
    public array $newFinding = [];

    public function mount(SiteInspection $inspection): void
    {
        $this->authorize('view', $inspection);
        $this->inspection = $inspection;
        $this->resetNewFinding();
    }

    public function submit(SubmitInspection $submit): void
    {
        $this->run(fn () => $submit->handle($this->actor(), $this->inspection), __('Inspection submitted.'));
    }

    public function close(CloseInspection $close): void
    {
        $this->run(fn () => $close->handle($this->actor(), $this->inspection), __('Inspection closed.'));
    }

    public function delete(DeleteInspection $delete): void
    {
        try {
            $delete->handle($this->actor(), $this->inspection);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        session()->flash('success', __('Inspection deleted.'));
        $this->redirectRoute('site.inspections.index', navigate: true);
    }

    public function addFinding(AddFinding $addFinding): void
    {
        $this->resetErrorBag();

        try {
            $addFinding->handle($this->actor(), $this->inspection, $this->newFinding);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        $this->resetNewFinding();
        $this->dispatch('close-sheet-finding-add');
        $this->dispatch('toast', type: 'success', description: __('Finding added.'));
    }

    /**
     * Open the status sheet for a finding, optionally with a target status.
     */
    public function openStatus(int $findingId, string $code = ''): void
    {
        $finding = $this->finding($findingId);
        $this->statusFindingId = $finding->id;
        $this->statusCode = $code;
        $this->reset('statusNote', 'statusPhoto');
        $this->resetErrorBag();
        $this->dispatch('open-sheet-finding-status');
    }

    public function changeStatus(ChangeFindingStatus $changeStatus): void
    {
        $this->resetErrorBag();
        $this->validate(['statusPhoto' => ['nullable', 'image', 'max:10240']]);

        try {
            $changeStatus->handle($this->actor(), $this->finding((int) $this->statusFindingId), $this->statusCode, $this->statusNote, $this->statusPhoto);
        } catch (ValidationException $exception) {
            $this->addError('statusNote', (string) collect($exception->errors())->flatten()->first());

            return;
        } catch (AuthorizationException) {
            $this->addError('statusNote', __('You cannot change this finding.'));

            return;
        }

        $this->reset('statusFindingId', 'statusCode', 'statusNote', 'statusPhoto');
        $this->inspection->refresh();
        $this->dispatch('close-sheet-finding-status');
        $this->dispatch('toast', type: 'success', description: __('Finding updated.'));
    }

    public function startPhoto(int $findingId): void
    {
        $this->photoFindingId = $this->finding($findingId)->id;
        $this->reset('photo');
        $this->dispatch('open-sheet-finding-photo');
    }

    public function uploadPhoto(UploadAttachment $upload): void
    {
        $this->authorize('update', $this->inspection);
        $this->validate(['photo' => ['required', 'image', 'max:10240']], [], ['photo' => __('photo')]);
        $finding = $this->finding((int) $this->photoFindingId);

        $upload->handle($finding, $this->photo, $this->actor(), [
            'document_type_id' => DocumentType::query()->where('code', 'site_photo')->value('id'),
            'title' => $finding->location ?? __('Site photo'),
        ]);

        $this->reset('photo', 'photoFindingId');
        $this->dispatch('close-sheet-finding-photo');
        $this->dispatch('toast', type: 'success', description: __('Photo added.'));
    }

    private function finding(int $id): SiteInspectionFinding
    {
        return $this->inspection->findings()->findOrFail($id);
    }

    private function resetNewFinding(): void
    {
        $this->newFinding = [
            'location' => '', 'description' => '', 'finding' => '', 'finding_category_id' => '',
            'finding_severity_id' => FindingSeverity::query()->where('code', FindingSeverity::MEDIUM)->value('id'),
            'action_required' => '', 'responsible_type' => '', 'responsible_id' => '', 'due_date' => '', 'found_by_name' => '',
        ];
    }

    private function run(\Closure $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->inspection->refresh();
        $this->dispatch('toast', type: 'success', description: $message);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $inspection = $this->inspection->load([
            'project:id,project_number,name,project_manager_id,customer_id', 'type', 'status', 'engineer:id,full_name',
            'findings.severity', 'findings.status', 'findings.category', 'findings.responsibleEmployee:id,full_name', 'findings.attachments',
        ]);
        $statusFinding = $this->statusFindingId !== null ? $inspection->findings->firstWhere('id', $this->statusFindingId) : null;

        return view('livewire.estimation.inspections.show', [
            'figures' => [
                'open' => $inspection->findings->filter(fn ($finding) => ! $finding->status->is_closed)->count(),
                'total' => $inspection->findings->count(),
                'serious' => $inspection->findings->filter(fn ($finding) => ! $finding->status->is_closed && $finding->severity->requires_follow_up)->count(),
                'days' => (int) $inspection->inspection_date->diffInDays(today()),
            ],
            'statusFinding' => $statusFinding,
            'statusTargets' => $statusFinding === null ? collect() : FindingStatus::query()->ordered()->get()
                ->filter(fn (FindingStatus $status) => $status->id !== $statusFinding->finding_status_id
                    && ($statusFinding->status->is_closed ? $status->code === FindingStatus::OPEN : true)),
            'followUpSeverities' => FindingSeverity::query()->where('requires_follow_up', true)->pluck('id')->all(),
            'projectHasCustomer' => $inspection->project->customer_id !== null,
        ])
            ->title($inspection->inspection_number)
            ->layoutData(['back' => route('site.inspections.index')]);
    }
}
