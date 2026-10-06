<?php

use App\Modules\Catalog\Livewire\Materials;
use App\Modules\Catalog\Livewire\Services;
use App\Modules\Catalog\Livewire\WorkItems;
use Illuminate\Support\Facades\Route;

/*
| Catalog routes (docs/02 §4). Each route repeats its permission as `can:` middleware;
| Livewire actions authorize again.
*/

Route::middleware('app')->prefix('catalog')->name('catalog.')->group(function () {
    Route::redirect('business-lines', '/admin/master-data/business_lines')->middleware('can:catalog.business_lines.view')->name('business-lines.index');
    Route::livewire('services', Services\Index::class)->middleware('can:catalog.services.view')->name('services.index');
    Route::livewire('services/create', Services\Form::class)->middleware('can:catalog.services.create')->name('services.create');
    Route::livewire('services/{service:code}/edit', Services\Form::class)->middleware('can:catalog.services.update')->name('services.edit');
    Route::livewire('work-items', WorkItems\Index::class)->middleware('can:catalog.work_items.view')->name('work-items.index');
    Route::livewire('work-items/import', WorkItems\Import::class)->middleware('can:catalog.work_items.import')->name('work-items.import');
    Route::livewire('work-items/create', WorkItems\Form::class)->middleware('can:catalog.work_items.create')->name('work-items.create');
    Route::livewire('work-items/{workItem:code}/edit', WorkItems\Form::class)->middleware('can:catalog.work_items.update')->name('work-items.edit');
    Route::livewire('materials', Materials\Index::class)->middleware('can:catalog.materials.view')->name('materials.index');
    Route::livewire('materials/import', Materials\Import::class)->middleware('can:catalog.materials.import')->name('materials.import');
    Route::livewire('materials/create', Materials\Form::class)->middleware('can:catalog.materials.create')->name('materials.create');
    Route::livewire('materials/{material:code}/edit', Materials\Form::class)->middleware('can:catalog.materials.update')->name('materials.edit');
    Route::redirect('units', '/admin/master-data/units')->middleware('can:catalog.units.view')->name('units.index');
});
