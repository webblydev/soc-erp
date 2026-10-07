<?php

namespace App\Modules\Projects\Livewire\Projects;

use App\Models\User;
use App\Modules\Projects\Actions\AddTeamMember;
use App\Modules\Projects\Actions\ReleaseTeamMember;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Team tab of the project page (docs/04 §5.3 tab 3, spec P13).
 */
class TeamTab extends Component
{
    public Project $project;

    /** @var array{employee_id: int|string|null, project_role_id: int|string|null, allocation_pct: string, assigned_on: string, notes: string} */
    public array $memberForm = ['employee_id' => null, 'project_role_id' => null, 'allocation_pct' => '', 'assigned_on' => '', 'notes' => ''];

    public ?int $releasingId = null;

    public string $releasedOn = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->memberForm['assigned_on'] = today()->toDateString();
    }

    public function addMember(AddTeamMember $addTeamMember): void
    {
        $this->resetErrorBag();

        try {
            $addTeamMember->handle($this->actor(), $this->project, array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $this->memberForm));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["memberForm.{$key}" => $messages])->all());
        }

        $this->memberForm = ['employee_id' => null, 'project_role_id' => null, 'allocation_pct' => '', 'assigned_on' => today()->toDateString(), 'notes' => ''];
        $this->dispatch('close-sheet-team-add');
        $this->dispatch('toast', type: 'success', description: __('Team member added.'));
    }

    public function confirmRelease(int $memberId): void
    {
        $this->releasingId = $this->project->activeTeam()->findOrFail($memberId)->id;
        $this->releasedOn = today()->toDateString();
        $this->dispatch('open-sheet-team-release');
    }

    public function release(ReleaseTeamMember $releaseTeamMember): void
    {
        $member = $this->project->team()->findOrFail($this->releasingId);

        try {
            $releaseTeamMember->handle($this->actor(), $member, ['released_on' => $this->releasedOn]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['releasedOn' => collect($exception->errors())->flatten()->all()]);
        }

        $this->releasingId = null;
        $this->dispatch('close-sheet-team-release');
        $this->dispatch('toast', type: 'success', description: __('Team member released.'));
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $members = $this->project->team()->with(['employee.designation:id,name', 'role'])->orderByDesc('is_active')->orderBy('assigned_on')->get();

        return view('livewire.projects.projects.team-tab', [
            'active' => $members->where('is_active', true)->values(),
            'past' => $members->where('is_active', false)->values(),
            'canManage' => $this->actor()->can('manageTeam', $this->project),
        ]);
    }
}
