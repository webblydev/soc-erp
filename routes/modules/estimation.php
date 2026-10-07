<?php

use App\Modules\Estimation\Exports\EstimateLinesExport;
use App\Modules\Estimation\Livewire\Budget;
use App\Modules\Estimation\Livewire\Estimates;
use App\Modules\Estimation\Livewire\Findings;
use App\Modules\Estimation\Livewire\Inspections;
use App\Modules\Estimation\Livewire\Mb;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Foundation\Models\CompanyProfile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

/*
| Estimation & Site routes (docs/05 §5, spec §4.2). Each route repeats its permission or policy as
| `can:` middleware; Livewire actions and Actions authorize again. Fixed segments stay above
| estimates/{estimate}.
*/

Route::middleware('app')->group(function () {
    Route::name('estimation.')->prefix('estimates')->group(function () {
        Route::livewire('/', Estimates\Index::class)->middleware('can:viewAny,'.Estimate::class)->name('estimates.index');
        Route::livewire('create', Estimates\Editor::class)->middleware('can:create,'.Estimate::class)->name('estimates.create');
        Route::livewire('{estimate:estimate_number}', Estimates\Show::class)->middleware('can:view,estimate')->name('estimates.show');
        Route::livewire('{estimate:estimate_number}/edit', Estimates\Editor::class)->middleware('can:update,estimate')->name('estimates.edit');
        Route::livewire('{estimate:estimate_number}/compare', Estimates\Compare::class)->middleware('can:view,estimate')->name('estimates.compare');

        Route::get('{estimate:estimate_number}/print', function (Estimate $estimate) {
            $layout = in_array(request()->query('layout'), ['measurement', 'abstract', 'materials'], true)
                ? request()->query('layout')
                : ($estimate->hasWorkLines() ? 'abstract' : 'materials');

            return view('estimation.print', [
                'estimate' => $estimate->load(['kind', 'status', 'project', 'preparer', 'checker', 'lines.section', 'lines.unit', 'materialLines.material', 'materialLines.unit']),
                'layout' => $layout,
                'company' => CompanyProfile::current(),
            ]);
        })->middleware('can:print,estimate')->name('estimates.print');

        Route::get('{estimate:estimate_number}/export', fn (Estimate $estimate) => Excel::download(new EstimateLinesExport($estimate), $estimate->estimate_number.'.xlsx'))
            ->middleware('can:export,estimate')->name('estimates.export');
    });

    Route::name('site.')->prefix('site')->group(function () {
        Route::livewire('mb', Mb\Index::class)->middleware('can:viewAny,'.MeasurementEntry::class)->name('mb.index');
        Route::livewire('mb/create', Mb\Form::class)->middleware('can:create,'.MeasurementEntry::class)->name('mb.create');
        Route::livewire('mb/{entry:mb_number}', Mb\Show::class)->middleware('can:view,entry')->name('mb.show');
        Route::livewire('mb/{entry:mb_number}/edit', Mb\Form::class)->middleware('can:update,entry')->name('mb.edit');

        Route::livewire('inspections', Inspections\Index::class)->middleware('can:viewAny,'.SiteInspection::class)->name('inspections.index');
        Route::livewire('inspections/create', Inspections\Form::class)->middleware('can:create,'.SiteInspection::class)->name('inspections.create');
        Route::livewire('inspections/{inspection:inspection_number}', Inspections\Show::class)->middleware('can:view,inspection')->name('inspections.show');
        Route::livewire('inspections/{inspection:inspection_number}/edit', Inspections\Form::class)->middleware('can:update,inspection')->name('inspections.edit');
        Route::get('inspections/{inspection:inspection_number}/print', fn (SiteInspection $inspection) => view('estimation.inspection-print', [
            'inspection' => $inspection->load(['project', 'type', 'status', 'engineer', 'findings.severity', 'findings.status', 'findings.responsibleEmployee', 'findings.attachments']),
            'company' => CompanyProfile::current(),
        ]))->middleware('can:print,inspection')->name('inspections.print');

        Route::livewire('findings', Findings\Board::class)->middleware('can:viewAny,'.SiteInspection::class)->name('findings.index');
    });

    Route::livewire('projects/{project:project_number}/budget', Budget\Show::class)->middleware('can:viewBudget,project')->name('estimation.budget.show');
});
