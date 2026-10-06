<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Hrm\Actions\ExitEmployee;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Services\ExitChecks;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Exit wizard (docs/09 §4.4, spec H7): details, then the exit checks and confirm. Blocking
 * checks keep Confirm disabled; ExitEmployee refuses them again.
 */
class ExitWizard extends Component
{
    public Employee $employee;

    #[Locked]
    public int $step = 1;

    public int|string|null $employee_status_id = null;

    public string $exit_date = '';

    public int|string|null $exit_reason_id = null;

    public string $note = '';

    public function mount(Employee $employee): void
    {
        $this->authorize('deactivate', $employee);

        if ($employee->hasExited()) {
            $this->redirectRoute('hrm.employees.show', $employee);

            return;
        }

        $this->employee = $employee;
        $this->exit_date = today()->toDateString();
        $this->employee_status_id = EmployeeStatus::query()->where('code', EmployeeStatus::RESIGNED)->value('id');
    }

    public function next(): void
    {
        $this->authorize('deactivate', $this->employee);

        $this->validate([
            'employee_status_id' => ['required', new ActiveLookup('employee_statuses', null, fn (Builder $query) => $query->where('is_exit', true))],
            'exit_date' => ['required', 'date', 'after_or_equal:'.$this->employee->joining_date->toDateString()],
            'exit_reason_id' => ['required', new ActiveLookup('exit_reasons')],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'exit_date.after_or_equal' => __('The exit date cannot be before the joining date.'),
        ], [
            'employee_status_id' => __('exit status'),
            'exit_reason_id' => __('reason'),
        ]);

        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function confirm(ExitEmployee $exitEmployee): void
    {
        $this->authorize('deactivate', $this->employee);

        try {
            $exitEmployee->handle($this->actor(), $this->employee, $this->only(['employee_status_id', 'exit_date', 'exit_reason_id', 'note']));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (array_intersect(array_keys($errors), ['employee_status_id', 'exit_date', 'exit_reason_id', 'note']) !== []) {
                $this->step = 1;

                throw $exception;
            }

            $this->dispatch('toast', type: 'error', description: (string) collect($errors)->flatten()->first());

            return;
        }

        session()->flash('success', __('Employee exited.'));
        $this->redirectRoute('hrm.employees.show', $this->employee, navigate: true);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(ExitChecks $exitChecks): View
    {
        $checks = $this->step === 2 ? $exitChecks->run($this->employee) : [];

        return view('livewire.hrm.employees.exit-wizard', [
            'checks' => $checks,
            'blocked' => collect($checks)->contains(fn ($item) => $item->blocking),
            'exitStatuses' => EmployeeStatus::query()->active()->where('is_exit', true)->ordered()->get(['id', 'name']),
        ])
            ->title(__('Exit :name', ['name' => $this->employee->full_name]))
            ->layoutData(['back' => route('hrm.employees.show', $this->employee), 'bottomNav' => false]);
    }
}
