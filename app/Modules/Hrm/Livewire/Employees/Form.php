<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Hrm\Actions\CreateEmployee;
use App\Modules\Hrm\Actions\UpdateEmployee;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmploymentEventType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create / edit an employee (docs/09 §4.2). Personal and education fields need full visibility,
 * bank and salary fields salary visibility (spec H2). Changing department, designation or salary
 * on an existing employee asks for an employment event in a sheet (spec H5).
 */
class Form extends Component
{
    use WithFileUploads;

    private const FIELDS = [
        'employee_code', 'first_name', 'last_name', 'father_name', 'mother_name', 'gender_id', 'date_of_birth', 'marital_status_id',
        'blood_group_id', 'nid_number', 'phone', 'personal_email', 'official_email', 'present_address', 'permanent_address',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone', 'reference', 'department_id', 'designation_id',
        'employee_type_id', 'employee_status_id', 'branch_id', 'manager_id', 'joining_date', 'confirmation_date', 'gross_salary',
        'bank_name', 'bank_account_no', 'mobile_wallet_no', 'tin', 'notes',
    ];

    public ?Employee $employee = null;

    public string $employee_code = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $father_name = '';

    public string $mother_name = '';

    public int|string|null $gender_id = null;

    public string $date_of_birth = '';

    public int|string|null $marital_status_id = null;

    public int|string|null $blood_group_id = null;

    public string $nid_number = '';

    public string $phone = '';

    public string $personal_email = '';

    public string $official_email = '';

    public string $present_address = '';

    public string $permanent_address = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_relation = '';

    public string $emergency_contact_phone = '';

    public string $reference = '';

    public int|string|null $department_id = null;

    public int|string|null $designation_id = null;

    public int|string|null $employee_type_id = null;

    public int|string|null $employee_status_id = null;

    public int|string|null $branch_id = null;

    public int|string|null $manager_id = null;

    public string $joining_date = '';

    public string $confirmation_date = '';

    public string $gross_salary = '';

    public string $bank_name = '';

    public string $bank_account_no = '';

    public string $mobile_wallet_no = '';

    public string $tin = '';

    public string $notes = '';

    /** @var UploadedFile|null */
    public $photo = null;

    /** @var list<array<string, mixed>> */
    public array $education = [];

    /** @var list<array<string, mixed>> */
    public array $experience = [];

    /** @var array{employment_event_type_id: int|string|null, effective_date: string, note: string} */
    public array $event = ['employment_event_type_id' => null, 'effective_date' => '', 'note' => ''];

    public bool $canViewFull = false;

    public bool $canViewSalary = false;

    public bool $canEditSalary = false;

    public function mount(?Employee $employee = null): void
    {
        $actor = $this->actor();

        if ($employee === null || ! $employee->exists) {
            $this->authorize('create', Employee::class);
            $this->canViewFull = $actor->can('hrm.employees.view_full');
            $this->canViewSalary = $actor->can('hrm.employees.view_salary') || $actor->can('hrm.employees.update_salary');
            $this->canEditSalary = $actor->can('hrm.employees.update_salary');
            $this->employee_status_id = EmployeeStatus::query()->where('code', EmployeeStatus::ACTIVE)->value('id');
            $this->joining_date = today()->toDateString();

            return;
        }

        $this->authorize('update', $employee);

        $this->employee = $employee;
        $this->canViewFull = $actor->can('viewFull', $employee);
        $this->canViewSalary = $actor->can('viewSalary', $employee);
        $this->canEditSalary = $actor->can('updateSalary', $employee);

        foreach (self::FIELDS as $field) {
            $value = $employee->getAttribute($field);
            $this->{$field} = match (true) {
                str_ends_with($field, '_id') => $value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                default => (string) $value,
            };
        }

        $this->gross_salary = $employee->gross_salary !== null ? rtrim(rtrim($employee->gross_salary, '0'), '.') : '';
        $this->education = array_values($employee->education()->get()->map(fn ($row): array => [
            'id' => $row->id, 'institution' => $row->institution, 'degree' => $row->degree,
            'from_year' => (string) $row->from_year, 'to_year' => (string) $row->to_year, 'result' => (string) $row->result,
        ])->all());
        $this->experience = array_values($employee->experience()->get()->map(fn ($row): array => [
            'id' => $row->id, 'company' => $row->company, 'position' => $row->position,
            'from_date' => (string) $row->from_date?->format('Y-m-d'), 'to_date' => (string) $row->to_date?->format('Y-m-d'), 'notes' => (string) $row->notes,
        ])->all());
    }

    public function addEducation(): void
    {
        $this->education[] = ['id' => null, 'institution' => '', 'degree' => '', 'from_year' => '', 'to_year' => '', 'result' => ''];
    }

    public function removeEducation(int $index): void
    {
        unset($this->education[$index]);
        $this->education = array_values($this->education);
    }

    public function addExperience(): void
    {
        $this->experience[] = ['id' => null, 'company' => '', 'position' => '', 'from_date' => '', 'to_date' => '', 'notes' => ''];
    }

    public function removeExperience(int $index): void
    {
        unset($this->experience[$index]);
        $this->experience = array_values($this->experience);
    }

    public function sameAsPresent(): void
    {
        $this->permanent_address = $this->present_address;
    }

    public function save(CreateEmployee $createEmployee, UpdateEmployee $updateEmployee): void
    {
        $this->employee === null ? $this->authorize('create', Employee::class) : $this->authorize('update', $this->employee);
        $this->resetErrorBag();

        if ($this->employee === null) {
            $employee = $createEmployee->handle($this->actor(), $this->input(), $this->photo);
            $this->finish($employee, __('Employee created.'));

            return;
        }

        try {
            $employee = $updateEmployee->handle($this->actor(), $this->employee, $this->input(), null, $this->photo);
        } catch (ValidationException $exception) {
            if (! array_key_exists('event', $exception->errors())) {
                throw $exception;
            }

            $this->event['effective_date'] = $this->event['effective_date'] !== '' ? $this->event['effective_date'] : today()->toDateString();
            $this->dispatch('open-sheet-employment-event');

            return;
        }

        $this->finish($employee, __('Employee saved.'));
    }

    public function saveWithEvent(UpdateEmployee $updateEmployee): void
    {
        abort_if($this->employee === null, 404);
        $this->authorize('update', $this->employee);
        $this->resetErrorBag();

        try {
            $employee = $updateEmployee->handle($this->actor(), $this->employee, $this->input(), $this->event, $this->photo);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => [in_array($key, ['employment_event_type_id', 'effective_date', 'note'], true) ? 'event.'.$key : $key => $messages],
            )->all());
        }

        $this->dispatch('close-sheet-employment-event');
        $this->finish($employee, __('Employee saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [...$this->only(self::FIELDS), 'education' => $this->education, 'experience' => $this->experience];
    }

    private function finish(Employee $employee, string $message): void
    {
        session()->flash('success', $message);

        $this->redirectRoute('hrm.employees.show', $employee, navigate: true);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.hrm.employees.form', [
            'statuses' => EmployeeStatus::query()
                ->where(fn (Builder $query) => $query->where('is_active', true)->where('is_exit', false)->when($this->employee, fn (Builder $query, Employee $employee) => $query->orWhere('id', $employee->employee_status_id)))
                ->ordered()->get(['id', 'name']),
            'eventTypes' => EmploymentEventType::query()->active()->whereNotIn('code', EmploymentEventType::RESERVED)->ordered()->get(['id', 'name']),
        ])
            ->title($this->employee === null ? __('New employee') : __('Edit employee'))
            ->layoutData(['back' => $this->employee !== null ? route('hrm.employees.show', $this->employee) : route('hrm.employees.index'), 'bottomNav' => false]);
    }
}
