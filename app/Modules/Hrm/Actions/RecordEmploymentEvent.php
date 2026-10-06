<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentEvent;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records an employment event and applies its new department, designation or salary in one
 * transaction (docs/09 §3.4, HR-BR-05, spec H5). A CONFIRMED event also sets the confirmation
 * date. JOINED, RESIGNED, TERMINATED, RETIRED and REJOINED are written by their own Actions
 * through write().
 */
class RecordEmploymentEvent
{
    /**
     * @param  array<string, mixed>  $input  employment_event_type_id, effective_date, note, and optionally
     *                                       department_id, designation_id, gross_salary
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, array $input): EmploymentEvent
    {
        if (! Gate::forUser($actor)->any(['manageHistory', 'update'], $employee)) {
            throw new AuthorizationException;
        }

        $input = array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value, $input);

        if (is_string($input['gross_salary'] ?? null)) {
            $input['gross_salary'] = str_replace(',', '', $input['gross_salary']);
        }

        if (! $actor->can('updateSalary', $employee)) {
            unset($input['gross_salary']);
        }

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, [
            'employment_event_type_id' => ['required', new ActiveLookup('employment_event_types', null, fn (Builder $query) => $query->whereNotIn('code', EmploymentEventType::RESERVED))],
            'effective_date' => ['required', 'date', 'after_or_equal:'.$employee->joining_date->toDateString()],
            'note' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', new ActiveLookup('departments', $employee->department_id)],
            'designation_id' => ['nullable', new ActiveLookup('designations', $employee->designation_id)],
            'gross_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
        ], [
            'effective_date.after_or_equal' => __('The effective date cannot be before the joining date.'),
        ], [
            'employment_event_type_id' => __('event'),
        ])->validate();

        $changes = array_intersect_key($data, array_flip(['department_id', 'designation_id', 'gross_salary']));
        $typeId = (int) $data['employment_event_type_id'];

        return DB::transaction(function () use ($actor, $employee, $typeId, $data, $changes): EmploymentEvent {
            $event = $this->write($actor, $employee, $typeId, (string) $data['effective_date'], $data['note'] ?? null, $changes);

            if ($typeId === EmploymentEventType::idFor(EmploymentEventType::CONFIRMED)) {
                $employee->forceFill(['confirmation_date' => $data['effective_date'], 'probation_notified_at' => null])->save();
            }

            return $event;
        });
    }

    /**
     * Writes the event with from / to values for the changed job fields and applies them.
     * $initial records only the to values (JOINED).
     *
     * @param  array<string, mixed>  $changes  department_id, designation_id and / or gross_salary
     */
    public function write(User $actor, Employee $employee, int $typeId, string $effectiveDate, ?string $note, array $changes = [], bool $initial = false): EmploymentEvent
    {
        $suffixes = ['department_id' => 'department_id', 'designation_id' => 'designation_id', 'gross_salary' => 'salary'];
        $attributes = [
            'employment_event_type_id' => $typeId,
            'effective_date' => $effectiveDate,
            'note' => $note,
            'approved_by' => $actor->id,
        ];

        $changes = array_filter(
            array_intersect_key($changes, $suffixes),
            fn (mixed $value, string $field): bool => $initial || self::differs($employee->getAttribute($field), $value),
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($changes as $field => $value) {
            $attributes['from_'.$suffixes[$field]] = $initial ? null : $employee->getAttribute($field);
            $attributes['to_'.$suffixes[$field]] = $value;
        }

        $employee->forceFill($changes)->save();

        /** @var EmploymentEvent */
        return $employee->events()->create($attributes);
    }

    /**
     * Whether a job field value changes, comparing ids as integers and salaries to the paisa.
     */
    public static function differs(mixed $current, mixed $new): bool
    {
        if ($current === null || $new === null || $current === '' || $new === '') {
            return ($current === null || $current === '') !== ($new === null || $new === '');
        }

        return number_format((float) $current, 2, '.', '') !== number_format((float) $new, 2, '.', '');
    }
}
