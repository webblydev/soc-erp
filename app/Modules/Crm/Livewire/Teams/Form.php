<?php

namespace App\Modules\Crm\Livewire\Teams;

use App\Models\User;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Actions\SaveSalesTeam;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Create / edit a sales team with its current members (docs/03 §5.8, spec R7).
 */
class Form extends Component
{
    public ?SalesTeam $team = null;

    public string $name = '';

    public int|string|null $manager_user_id = null;

    public int|string|null $business_line_id = null;

    public string $monthly_target_amount = '';

    public bool $is_active = true;

    /** @var list<array{user_id: int|string|null, joined_on: string}> */
    public array $members = [];

    public function mount(?SalesTeam $team = null): void
    {
        $this->authorize('crm.teams.manage');

        if ($team === null || ! $team->exists) {
            return;
        }

        $this->team = $team;
        $this->fill($team->only(['name', 'manager_user_id', 'business_line_id', 'is_active']));
        $this->monthly_target_amount = (string) $team->monthly_target_amount;
        $this->members = $team->activeMembers()->orderBy('joined_on')->get()
            ->map(fn ($member): array => ['user_id' => $member->user_id, 'joined_on' => $member->joined_on->toDateString()])
            ->all();
    }

    public function addMember(): void
    {
        $this->members[] = ['user_id' => null, 'joined_on' => today()->toDateString()];
    }

    public function removeMember(int $index): void
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function save(SaveSalesTeam $saveSalesTeam): void
    {
        $this->authorize('crm.teams.manage');

        /** @var User $actor */
        $actor = auth()->user();

        $saveSalesTeam->handle($actor, [
            ...$this->only(['name', 'manager_user_id', 'business_line_id', 'monthly_target_amount', 'is_active']),
            'members' => array_map(fn (array $member): array => ['user_id' => (int) $member['user_id'], 'joined_on' => $member['joined_on']], $this->members),
        ], $this->team);

        session()->flash('success', $this->team === null ? __('Team created.') : __('Team saved.'));

        $this->redirectRoute('crm.teams.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.crm.teams.form', [
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'businessLines' => BusinessLine::query()
                ->where(fn ($query) => $query->where('is_active', true)->where('is_internal', false)->orWhere('id', $this->team?->business_line_id))
                ->ordered()->get(['id', 'name']),
            'pastMembers' => $this->team?->members()->whereNotNull('left_on')->with('user:id,name')->latest('left_on')->get() ?? collect(),
        ])
            ->title($this->team === null ? __('New sales team') : __('Edit sales team'))
            ->layoutData(['back' => route('crm.teams.index'), 'bottomNav' => false]);
    }
}
