<?php

namespace App\Modules\Projects\Livewire\Approvals;

use App\Models\User;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Projects\Actions\SaveApproval;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New / edit approval (docs/04 §3.10, spec P18). The status changes on the approval page.
 */
class Form extends Component
{
    use SavesFromDetailModal;

    private const FIELDS = ['approval_authority_id', 'approval_type_id', 'reference_no', 'responsible_employee_id', 'authority_fee', 'notes'];

    private const DATES = ['prepared_on', 'submitted_on', 'expected_on', 'valid_until'];

    public ?ProjectApproval $approval = null;

    #[Url(as: 'project', except: '')]
    public string $projectNumber = '';

    public int|string|null $project_id = null;

    public int|string|null $approval_authority_id = null;

    public int|string|null $approval_type_id = null;

    public string $reference_no = '';

    public int|string|null $responsible_employee_id = null;

    public string $prepared_on = '';

    public string $submitted_on = '';

    public string $expected_on = '';

    public string $valid_until = '';

    public string $authority_fee = '';

    public string $notes = '';

    public function mount(?ProjectApproval $approval = null): void
    {
        if ($approval === null || ! $approval->exists) {
            $this->authorize('projects.approvals.manage');

            $project = $this->projectNumber !== '' ? Project::query()->where('project_number', $this->projectNumber)->first() : null;

            if ($project !== null && $this->actor()->can('manageApprovals', $project)) {
                $this->project_id = $project->id;
            }

            $this->prepared_on = today()->toDateString();
            $this->responsible_employee_id = $this->actor()->employee_id;

            return;
        }

        $this->authorize('update', $approval);

        $this->approval = $approval;
        $this->project_id = $approval->project_id;

        foreach (self::FIELDS as $field) {
            $this->{$field} = $approval->getAttribute($field) ?? (in_array($field, ['reference_no', 'authority_fee', 'notes'], true) ? '' : null);
        }

        foreach (self::DATES as $field) {
            $this->{$field} = (string) $approval->getAttribute($field)?->toDateString();
        }
    }

    public function save(SaveApproval $saveApproval): void
    {
        $project = Project::query()->find((int) $this->project_id);

        if ($project === null || ! Gate::allows('manageApprovals', $project)) {
            throw ValidationException::withMessages(['project_id' => __('Choose a project you manage approvals for.')]);
        }

        $approval = $saveApproval->handle($this->actor(), $project, $this->only([...self::FIELDS, ...self::DATES]), $this->approval);

        $this->redirectAfterSave(__('Approval saved.'), 'projects.approvals.show', $approval);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.projects.approvals.form', [
            'projects' => $this->approval === null
                ? Project::query()->visibleTo($this->actor())->open()->orderBy('project_number')->get(['id', 'project_number', 'name'])->filter(fn (Project $project): bool => $this->actor()->can('manageApprovals', $project))->values()
                : collect(),
        ])
            ->title($this->approval === null ? __('New approval') : __('Edit approval'))
            ->layoutData(['back' => $this->approval === null ? route('projects.approvals.index') : route('projects.approvals.show', $this->approval), 'bottomNav' => false]);
    }
}
