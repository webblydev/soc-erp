<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Hrm\Events\EmployeeDeactivated;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Notifications\ExitChecklist;
use App\Modules\Hrm\Services\ExitChecks;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records an employee leaving (docs/09 §4.4, HR-AC-03, spec H7): exit status, date and reason,
 * the matching employment event, and the linked login switched off, all in one transaction.
 * Blocking exit checks stop it.
 */
class ExitEmployee
{
    public function __construct(private ExitChecks $checks, private RecordEmploymentEvent $events, private SetUserActive $setUserActive) {}

    /**
     * @param  array<string, mixed>  $input  employee_status_id, exit_date, exit_reason_id, note
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, array $input): Employee
    {
        Gate::forUser($actor)->authorize('deactivate', $employee);

        if ($employee->hasExited()) {
            throw ValidationException::withMessages(['employee_status_id' => __('This employee has already left.')]);
        }

        $data = $this->validate($employee, $input);

        if ($this->checks->blocks($employee)) {
            throw ValidationException::withMessages(['checks' => __('Resolve the blocking items before this employee can leave.')]);
        }

        DB::transaction(function () use ($actor, $employee, $data): void {
            $status = EmployeeStatus::query()->findOrFail((int) $data['employee_status_id']);

            $employee->forceFill([
                'employee_status_id' => $status->id,
                'exit_date' => $data['exit_date'],
                'exit_reason_id' => (int) $data['exit_reason_id'],
            ])->save();

            $this->events->write($actor, $employee, $this->eventTypeFor($status), (string) $data['exit_date'], $data['note'] ?? null);

            $user = $employee->user()->first();

            if ($user !== null && $user->is_active) {
                $this->setUserActive->handle($user, false, $actor);
            }

            EmployeeDeactivated::dispatch($employee, $actor);
        });

        Notification::send($this->recipients($actor), new ExitChecklist($employee));

        return $employee->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Employee $employee, array $input): array
    {
        $input = array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value, $input);

        /** @var array<string, mixed> */
        return Validator::make($input, [
            'employee_status_id' => ['required', new ActiveLookup('employee_statuses', null, fn (Builder $query) => $query->where('is_exit', true))],
            'exit_date' => ['required', 'date', 'after_or_equal:'.$employee->joining_date->toDateString()],
            'exit_reason_id' => ['required', new ActiveLookup('exit_reasons')],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'exit_date.after_or_equal' => __('The exit date cannot be before the joining date.'),
        ], [
            'employee_status_id' => __('exit status'),
            'exit_reason_id' => __('reason'),
        ])->validate();
    }

    /**
     * RESIGNED, TERMINATED and RETIRED have event types of the same code; an exit status added
     * later records RESIGNED.
     */
    private function eventTypeFor(EmployeeStatus $status): int
    {
        $code = in_array($status->code, [EmploymentEventType::TERMINATED, EmploymentEventType::RETIRED], true) ? $status->code : EmploymentEventType::RESIGNED;

        return EmploymentEventType::idFor($code);
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(User $actor): Collection
    {
        return User::query()->where('is_active', true)->whereKeyNot($actor->id)->get()
            ->filter(fn (User $user): bool => $user->can('hrm.employees.deactivate'))
            ->values();
    }
}
