<?php

use App\Modules\Catalog\Livewire\Services;
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
    Route::redirect('units', '/admin/master-data/units')->middleware('can:catalog.units.view')->name('units.index');
});
