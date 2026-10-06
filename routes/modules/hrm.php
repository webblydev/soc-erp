<?php

use App\Modules\Hrm\Livewire\Documents;
use App\Modules\Hrm\Livewire\Employees;
use App\Modules\Hrm\Livewire\OrgChart;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\Route;

/*
| HRM routes (docs/09 §4). Each route repeats its permission or policy as `can:` middleware;
| Livewire actions and Actions authorize again.
*/

Route::middleware('app')->prefix('hrm')->name('hrm.')->group(function () {
    // Routes with a fixed segment (create) must stay above employees/{employee}.
    Route::livewire('employees', Employees\Index::class)->middleware('can:viewAny,'.Employee::class)->name('employees.index');
    Route::livewire('employees/create', Employees\Form::class)->middleware('can:create,'.Employee::class)->name('employees.create');
    Route::livewire('employees/{employee:employee_code}', Employees\Show::class)->middleware('can:view,employee')->name('employees.show');
    Route::livewire('employees/{employee:employee_code}/edit', Employees\Form::class)->middleware('can:update,employee')->name('employees.edit');
    Route::livewire('employees/{employee:employee_code}/exit', Employees\ExitWizard::class)->middleware('can:deactivate,employee')->name('employees.exit');

    Route::livewire('org-chart', OrgChart::class)->middleware('can:viewAny,'.Employee::class)->name('org-chart');
    Route::livewire('documents/expiring', Documents\Expiring::class)->middleware('can:hrm.documents.manage')->name('documents.expiring');
});
