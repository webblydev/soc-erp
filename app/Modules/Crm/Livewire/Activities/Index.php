<?php

namespace App\Modules\Crm\Livewire\Activities;

use App\Models\User;
use App\Modules\Crm\Actions\DeleteActivity;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Foundation\Models\Role;
use Carbon\CarbonInterface;
use Carbon\WeekDay;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * My activities (docs/03 §5.5): Overdue · Today · Upcoming · Done, and a week calendar.
 * The week runs Saturday to Friday (the Bangladesh work week).
 */
#[Title('My activities')]
class Index extends Component
{
    use WithPagination;

    public const TABS = ['overdue', 'today', 'upcoming', 'done'];

    #[Url(except: 'today')]
    public string $tab = 'today';

    #[Url(except: 'list')]
    public string $view = 'list';

    #[Url(except: 'me')]
    public string $owner = 'me';

    #[Url(except: '')]
    public string $date = '';

    public ?int $deletingActivityId = null;

    public function mount(): void
    {
        $this->authorize('crm.activities.view');
        $this->guardOwner();
    }

    public function updatedOwner(): void
    {
        $this->guardOwner();
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function previousWeek(): void
    {
        $this->date = $this->weekStart()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->date = $this->weekStart()->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->date = '';
    }

    #[On('crm-activity-saved')]
    public function refreshList(): void {}

    public function confirmDeleteActivity(int $id): void
    {
        $this->deletingActivityId = $this->scoped()->findOrFail($id)->id;
        $this->dispatch('open-sheet-activity-delete');
    }

    public function deleteActivity(DeleteActivity $deleteActivity): void
    {
        $deleteActivity->handle($this->actor(), $this->scoped()->findOrFail($this->deletingActivityId));

        $this->deletingActivityId = null;
        $this->dispatch('close-sheet-activity-delete');
        $this->dispatch('toast', type: 'success', description: __('Activity deleted.'));
    }

    /**
     * Owners the user may filter by: themselves, then team members (view_team) or anyone (view_all).
     *
     * @return list<int>|null null means any owner
     */
    private function ownerScope(): ?array
    {
        $user = $this->actor();

        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasPermission('crm.activities.view_all')) {
            return null;
        }

        return $user->hasPermission('crm.activities.view_team')
            ? array_values(array_unique([$user->id, ...SalesTeam::managedMemberIds($user)]))
            : [$user->id];
    }

    /**
     * Resets an owner outside the user's scope to "me".
     */
    private function guardOwner(): void
    {
        $scope = $this->ownerScope();
        $wide = $scope === null || count($scope) > 1;

        $valid = match (true) {
            $this->owner === 'me' => true,
            $this->owner === 'all' => $wide,
            ctype_digit($this->owner) => $scope === null || in_array((int) $this->owner, $scope, true),
            default => false,
        };

        if (! $valid) {
            $this->owner = 'me';
        }
    }

    /**
     * @return Builder<CrmActivity>
     */
    private function scoped(): Builder
    {
        $query = CrmActivity::query()
            ->visibleTo($this->actor(), 'crm.activities')
            ->with(['type', 'outcome', 'owner:id,name', 'subject']);

        return match ($this->owner) {
            'all' => $query,
            'me' => $query->where('owner_user_id', $this->actor()->id),
            default => $query->where('owner_user_id', (int) $this->owner),
        };
    }

    /**
     * @return Builder<CrmActivity>
     */
    private function tabQuery(string $tab): Builder
    {
        $query = $this->scoped();

        return match ($tab) {
            'overdue' => $query->whereNull('completed_at')->whereNotNull('scheduled_at')->where('scheduled_at', '<', now())->orderBy('scheduled_at'),
            'upcoming' => $query->whereNull('completed_at')->where('scheduled_at', '>', today()->endOfDay())->orderBy('scheduled_at'),
            'done' => $query->whereNotNull('completed_at')->latest('completed_at'),
            default => $query->whereNull('completed_at')->whereBetween('scheduled_at', [now(), today()->endOfDay()])->orderBy('scheduled_at'),
        };
    }

    private function weekStart(): CarbonInterface
    {
        $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1 ? Carbon::parse($this->date) : today();

        return $anchor->copy()->startOfWeek(WeekDay::Saturday);
    }

    /**
     * Scheduled and done activities of the week in the x-ui.scheduler shape.
     *
     * @return list<array{title: string, day: int, start: string, end: string, color: string, subject: ?string, when: CarbonInterface, open: bool}>
     */
    private function weekEvents(CarbonInterface $start): array
    {
        $end = $start->copy()->addDays(7);

        return $this->scoped()
            ->where(fn (Builder $query) => $query->whereBetween('scheduled_at', [$start, $end])
                ->orWhere(fn (Builder $query) => $query->whereNull('scheduled_at')->whereBetween('completed_at', [$start, $end])))
            ->get()
            ->map(function (CrmActivity $activity) use ($start): array {
                /** @var CarbonInterface $when */
                $when = $activity->scheduled_at ?? $activity->completed_at;
                $minutes = max(30, (int) $activity->duration_minutes);

                return [
                    'title' => $activity->title,
                    'day' => (int) $start->diffInDays($when->copy()->startOfDay()),
                    'start' => $when->format('H:i'),
                    'end' => $when->copy()->addMinutes($minutes)->min($when->copy()->endOfDay())->format('H:i'),
                    'color' => $activity->isOverdue() ? 'rose' : ($activity->isOpen() ? 'sky' : 'emerald'),
                    'subject' => $activity->subject?->getAttribute('name'),
                    'when' => $when,
                    'open' => $activity->isOpen(),
                ];
            })
            ->sortBy('when')->values()->all();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'today';
        }

        $scope = $this->ownerScope();
        $weekStart = $this->weekStart();

        return view('livewire.crm.activities.index', [
            'activities' => $this->view === 'list'
                ? ($this->tab === 'done' ? $this->tabQuery('done')->paginate(25) : $this->tabQuery($this->tab)->limit(200)->get())
                : collect(),
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $tab): array => [$tab => $tab === 'done' ? null : $this->tabQuery($tab)->count()])->all(),
            'events' => $this->view === 'calendar' ? $this->weekEvents($weekStart) : [],
            'weekStart' => $weekStart,
            'days' => collect(range(0, 6))->map(fn (int $offset): string => $weekStart->copy()->addDays($offset)->format('D d M'))->all(),
            'owners' => $scope === null || count($scope) > 1
                ? User::query()->where('is_active', true)->when($scope !== null, fn ($query) => $query->whereKey($scope))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }
}
