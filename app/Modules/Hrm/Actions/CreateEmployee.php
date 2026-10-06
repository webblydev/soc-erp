<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Concerns\ValidatesEmployeeInput;
use App\Modules\Hrm\Events\EmployeeCreated;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Services\EmployeeFields;
use App\Support\NumberSequenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates an employee with a JOINED event (docs/09 §4.2, spec H3–H5). A blank code takes the
 * next `employee` number.
 */
class CreateEmployee
{
    use ValidatesEmployeeInput;

    public function __construct(private NumberSequenceService $numbers, private RecordEmploymentEvent $events) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?UploadedFile $photo = null): Employee
    {
        Gate::forUser($actor)->authorize('create', Employee::class);

        $data = $this->validateEmployee($this->restrict($actor, null, $this->normalise($input)), null);
        $photoPath = $this->storePhoto($photo);

        try {
            return DB::transaction(function () use ($actor, $data, $photoPath): Employee {
                $employee = new Employee;
                $employee->fill(Arr::only($data, $employee->getFillable()));
                $employee->forceFill([
                    'employee_code' => $data['employee_code'] ?? $this->numbers->next('employee'),
                    'full_name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    'photo_path' => $photoPath,
                    ...Arr::only($data, ['department_id', 'designation_id', ...EmployeeFields::SALARY]),
                ]);
                $employee->save();

                $this->syncRows($employee->education(), $data['education'] ?? [], self::EDUCATION_COLUMNS);
                $this->syncRows($employee->experience(), $data['experience'] ?? [], self::EXPERIENCE_COLUMNS);

                $this->events->write(
                    $actor,
                    $employee,
                    EmploymentEventType::idFor(EmploymentEventType::JOINED),
                    $employee->joining_date->toDateString(),
                    null,
                    Arr::only($employee->getAttributes(), array_keys(Arr::only($data, self::JOB_FIELDS))),
                    initial: true,
                );

                EmployeeCreated::dispatch($employee);

                return $employee;
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }
    }
}
