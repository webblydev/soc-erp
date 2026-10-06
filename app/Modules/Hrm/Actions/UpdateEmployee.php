<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Concerns\ValidatesEmployeeInput;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Edits an employee (docs/09 §4.2). The code never changes (spec H3). Department, designation
 * and salary changes need an employment event, passed as $event and recorded through
 * RecordEmploymentEvent (HR-BR-05, spec H5).
 */
class UpdateEmployee
{
    use ValidatesEmployeeInput;

    public function __construct(private RecordEmploymentEvent $events) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{employment_event_type_id?: mixed, effective_date?: mixed, note?: mixed}|null  $event
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Employee $employee, array $input, ?array $event = null, ?UploadedFile $photo = null): Employee
    {
        Gate::forUser($actor)->authorize('update', $employee);

        $input = Arr::except($this->restrict($actor, $employee, $this->normalise($input)), ['employee_code']);
        $data = $this->validateEmployee($input, $employee);

        $changed = array_filter(
            Arr::only($data, self::JOB_FIELDS),
            fn (mixed $value, string $field): bool => RecordEmploymentEvent::differs($employee->getAttribute($field), $value),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed !== [] && $event === null) {
            throw ValidationException::withMessages(['event' => __('Record an employment event for the department, designation or salary change.')]);
        }

        $photoPath = $this->storePhoto($photo);
        $oldPhoto = $employee->photo_path;

        try {
            DB::transaction(function () use ($actor, $employee, $data, $changed, $event, $photoPath): void {
                $confirmationBefore = $employee->confirmation_date?->toDateString();

                $employee->fill(Arr::only($data, $employee->getFillable()));
                $employee->forceFill([
                    'full_name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    ...Arr::only($data, array_diff(EmployeeFields::SALARY, self::JOB_FIELDS)),
                ]);

                if ($photoPath !== null) {
                    $employee->photo_path = $photoPath;
                }

                if ($employee->confirmation_date?->toDateString() !== $confirmationBefore) {
                    $employee->probation_notified_at = null;
                }

                $employee->save();

                if (array_key_exists('education', $data)) {
                    $this->syncRows($employee->education(), $data['education'], self::EDUCATION_COLUMNS);
                }

                if (array_key_exists('experience', $data)) {
                    $this->syncRows($employee->experience(), $data['experience'], self::EXPERIENCE_COLUMNS);
                }

                if ($changed !== [] && $event !== null) {
                    $this->events->handle($actor, $employee, [...$event, ...$changed]);
                }
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        if ($photoPath !== null && $oldPhoto !== null) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $employee->refresh();
    }
}
