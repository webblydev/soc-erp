<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Hrm\Actions\LinkUser;
use App\Modules\Hrm\Actions\RecordEmploymentEvent;
use App\Modules\Hrm\Actions\RejoinEmployee;
use App\Modules\Hrm\Actions\UnlinkUser;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Employee profile (docs/09 §4.3). Tabs follow field visibility (spec H2); Projects, Tasks and
 * Advances tabs arrive with modules 04 and 08 (spec H18).
 */
class Show extends Component
{
    public const TABS = ['overview', 'documents', 'events', 'education', 'salary', 'notes', 'history'];

    public Employee $employee;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** @var array{employment_event_type_id: int|string|null, effective_date: string, note: string} */
    public array $eventForm = ['employment_event_type_id' => null, 'effective_date' => '', 'note' => ''];

    /** @var array{effective_date: string, note: string} */
    public array $rejoinForm = ['effective_date' => '', 'note' => ''];

    public string $linkUserId = '';

    public function mount(Employee $employee): void
    {
        $this->authorize('view', $employee);
        $this->employee = $employee;
        $this->eventForm['effective_date'] = today()->toDateString();
        $this->rejoinForm['effective_date'] = today()->toDateString();
    }

    public function recordEvent(RecordEmploymentEvent $recordEmploymentEvent): void
    {
        $this->authorize('manageHistory', $this->employee);

        $this->withPrefixedErrors('eventForm', fn () => $recordEmploymentEvent->handle($this->actor(), $this->employee, $this->eventForm));

        $this->eventForm = ['employment_event_type_id' => null, 'effective_date' => today()->toDateString(), 'note' => ''];
        $this->employee->refresh();
        $this->dispatch('close-sheet-employment-event');
        $this->dispatch('toast', type: 'success', description: __('Event recorded.'));
    }

    public function rejoin(RejoinEmployee $rejoinEmployee): void
    {
        $this->authorize('deactivate', $this->employee);

        $this->withPrefixedErrors('rejoinForm', fn () => $rejoinEmployee->handle($this->actor(), $this->employee, $this->rejoinForm));

        $this->employee->refresh();
        $this->dispatch('close-sheet-rejoin');
        $this->dispatch('toast', type: 'success', description: __('Employee rejoined.'));
    }

    public function linkUser(LinkUser $linkUser): void
    {
        $this->authorize('admin.users.update');

        $user = User::query()->whereNull('employee_id')->find((int) $this->linkUserId);

        if ($user === null) {
            throw ValidationException::withMessages(['linkUserId' => __('Choose a user without an employee.')]);
        }

        $this->withPrefixedErrors(null, fn () => $linkUser->handle($this->actor(), $this->employee, $user), ['user_id' => 'linkUserId']);

        $this->linkUserId = '';
        $this->dispatch('close-sheet-link-user');
        $this->dispatch('toast', type: 'success', description: __('User linked.'));
    }

    public function unlinkUser(UnlinkUser $unlinkUser): void
    {
        $this->authorize('admin.users.update');

        $unlinkUser->handle($this->actor(), $this->employee);

        $this->dispatch('toast', type: 'success', description: __('User unlinked.'));
    }

    /**
     * Run an Action and show its validation errors under the given form prefix or key map.
     *
     * @param  array<string, string>  $map
     *
     * @throws ValidationException
     */
    private function withPrefixedErrors(?string $prefix, \Closure $callback, array $map = []): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => [$map[$key] ?? ($prefix !== null ? $prefix.'.'.$key : $key) => $messages],
            )->all());
        }
    }

    /**
     * Tabs the user may open (spec H2).
     *
     * @return array<string, string>
     */
    private function allowedTabs(): array
    {
        $actor = $this->actor();
        $full = $actor->can('viewFull', $this->employee);

        return array_filter([
            'overview' => __('Overview'),
            'documents' => $actor->can('viewDocuments', $this->employee) ? __('Documents') : null,
            'events' => __('Employment history'),
            'education' => $full ? __('Education & experience') : null,
            'salary' => $actor->can('viewSalary', $this->employee) ? __('Salary') : null,
            'notes' => $full ? __('Notes') : null,
            'history' => $full ? __('History') : null,
        ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        $tabs = $this->allowedTabs();

        if (! array_key_exists($this->tab, $tabs)) {
            $this->tab = 'overview';
        }

        $this->employee->load([
            'department', 'designation', 'type', 'status', 'branch', 'manager', 'user', 'gender', 'maritalStatus', 'bloodGroup', 'exitReason',
            'reports' => fn ($query) => $query->assignable()->with('designation:id,name'),
        ]);

        return view('livewire.hrm.employees.show', [
            'tabs' => $tabs,
            'canViewFull' => $this->actor()->can('viewFull', $this->employee),
            'canViewSalary' => $this->actor()->can('viewSalary', $this->employee),
            'events' => $this->tab === 'events'
                ? $this->employee->events()->with(['type', 'fromDepartment', 'toDepartment', 'fromDesignation', 'toDesignation', 'approver:id,name'])->get()
                : collect(),
            'education' => $this->tab === 'education' ? $this->employee->education()->get() : collect(),
            'experience' => $this->tab === 'education' ? $this->employee->experience()->get() : collect(),
            'eventTypes' => EmploymentEventType::query()->active()->whereNotIn('code', EmploymentEventType::RESERVED)->ordered()->get(['id', 'name']),
            'linkableUsers' => $this->employee->user === null && $this->actor()->can('admin.users.update')
                ? User::query()->where('is_active', true)->whereNull('employee_id')->orderBy('name')->get(['id', 'name', 'username'])
                : new Collection,
        ])
            ->title($this->employee->full_name)
            ->layoutData(['back' => route('hrm.employees.index')]);
    }
}
