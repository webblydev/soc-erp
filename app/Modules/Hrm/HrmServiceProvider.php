<?php

namespace App\Modules\Hrm;

use App\Modules\Hrm\Models\BloodGroup;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeEducation;
use App\Modules\Hrm\Models\EmployeeExperience;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Models\EmploymentEvent;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Models\Gender;
use App\Modules\Hrm\Models\MaritalStatus;
use App\Modules\Hrm\Policies\EmployeePolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class HrmServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap HRM services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/hrm'));

        Relation::morphMap([
            'department' => Department::class,
            'designation' => Designation::class,
            'employee_type' => EmployeeType::class,
            'employee_status' => EmployeeStatus::class,
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'blood_group' => BloodGroup::class,
            'employee_document_type' => EmployeeDocumentType::class,
            'employment_event_type' => EmploymentEventType::class,
            'exit_reason' => ExitReason::class,
            'employee' => Employee::class,
            'employee_document' => EmployeeDocument::class,
            'employment_event' => EmploymentEvent::class,
            'employee_education' => EmployeeEducation::class,
            'employee_experience' => EmployeeExperience::class,
        ]);

        Gate::policy(Employee::class, EmployeePolicy::class);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Hrm\\Livewire');
    }
}
