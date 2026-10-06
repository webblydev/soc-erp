<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Brings a former employee back (spec H7): status ACTIVE, exit fields cleared, REJOINED event.
 * The linked login stays off until an admin turns it on.
 */
class RejoinEmployee
{
    public function __construct(private RecordEmploymentEvent $events) {}

    /**
     * @param  array<string, mixed>  $input  effective_date, note
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, array $input): Employee
    {
        Gate::forUser($actor)->authorize('deactivate', $employee);

        if (! $employee->hasExited()) {
            throw ValidationException::withMessages(['employee_status_id' => __('Only a former employee can rejoin.')]);
        }

        /** @var array{effective_date: string, note?: string|null} $data */
        $data = Validator::make($input, [
            'effective_date' => ['required', 'date', 'after_or_equal:'.($employee->exit_date ?? $employee->joining_date)->toDateString()],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'effective_date.after_or_equal' => __('The rejoining date cannot be before the exit date.'),
        ])->validate();

        DB::transaction(function () use ($actor, $employee, $data): void {
            $employee->forceFill([
                'employee_status_id' => EmployeeStatus::idFor(EmployeeStatus::ACTIVE),
                'exit_date' => null,
                'exit_reason_id' => null,
            ])->save();

            $this->events->write($actor, $employee, EmploymentEventType::idFor(EmploymentEventType::REJOINED), $data['effective_date'], $data['note'] ?? null);
        });

        return $employee->refresh();
    }
}
