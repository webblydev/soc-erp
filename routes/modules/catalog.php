<?php

use Illuminate\Support\Facades\Route;

/*
| Catalog routes (docs/02 §4). Each route repeats its permission as `can:` middleware;
| Livewire actions authorize again.
*/

Route::middleware('app')->prefix('catalog')->name('catalog.')->group(function () {
    Route::redirect('business-lines', '/admin/master-data/business_lines')->middleware('can:catalog.business_lines.view')->name('business-lines.index');
    Route::redirect('units', '/admin/master-data/units')->middleware('can:catalog.units.view')->name('units.index');
});
