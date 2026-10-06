<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Hrm\Actions\DeleteEmployee;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Services\EmployeeFields;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Employee directory (docs/09 §4.1). Shows employees in active employment unless the status
 * filter says otherwise; the export follows the user's field visibility (spec H2).
 */
#[Title('Employees')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    #[Url(except: 'list')]
    public string $view = 'list';

    public function mount(): void
    {
        $this->authorize('viewAny', Employee::class);
    }

    /**
     * @return Builder<Employee>
     */
    protected function listingQuery(): Builder
    {
        return Employee::query()
            ->with(['department:id,name', 'designation:id,name', 'status:id,name,color'])
            ->orderBy('full_name');
    }

    protected function searchColumns(): array
    {
        return ['employees.employee_code', 'employees.full_name', 'employees.phone', 'employees.official_email'];
    }

    protected function sortColumns(): array
    {
        return ['code' => 'employees.employee_code', 'name' => 'employees.full_name', 'joined' => 'employees.joining_date'];
    }

    /**
     * @param  Builder<Employee>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        $status = $this->filterString('status');

        match (true) {
            $status === '' => $query->assignable(),
            $status === 'all' => null,
            default => $query->where('employees.employee_status_id', (int) $status),
        };

        foreach (['department' => 'department_id', 'designation' => 'designation_id', 'type' => 'employee_type_id', 'branch' => 'branch_id', 'manager' => 'manager_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('employees.'.$column, (int) $this->filterString($key));
            }
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('hrm.employees.export');

        return ListingExport::download('employees', $this->exportQuery()->with(['type:id,name', 'bloodGroup:id,name']), self::exportColumns($this->actor()));
    }

    /**
     * Export columns the user may see (spec H2).
     *
     * @return array<string, string>
     */
    public static function exportColumns(User $user): array
    {
        $visible = EmployeeFields::visibleTo($user);

        $columns = [
            'Code' => 'employee_code',
            'Name' => 'full_name',
            'Department' => 'department.name',
            'Designation' => 'designation.name',
            'Type' => 'type.name',
            'Status' => 'status.name',
            'Phone' => 'phone',
            'Official email' => 'official_email',
            'Joining date' => 'joining_date',
        ];

        if (in_array('nid_number', $visible, true)) {
            $columns += ['NID' => 'nid_number', 'Date of birth' => 'date_of_birth', 'Blood group' => 'bloodGroup.name', 'Present address' => 'present_address'];
        }

        if (in_array('gross_salary', $visible, true)) {
            $columns += ['Gross salary' => 'gross_salary', 'Bank' => 'bank_name', 'Account no.' => 'bank_account_no'];
        }

        return $columns;
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('hrm.employees.delete');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var Employee $row */
        app(DeleteEmployee::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        if (! in_array($this->view, ['list', 'cards'], true)) {
            $this->view = 'list';
        }

        return view('livewire.hrm.employees.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'statuses' => EmployeeStatus::query()->active()->ordered()->get(['id', 'name']),
            'managers' => Employee::query()->assignable()->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }
}
