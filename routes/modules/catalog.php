<?php

use Illuminate\Support\Facades\Route;

/*
| Catalog routes (docs/02 §4). Each route repeats its permission as `can:` middleware;
| Livewire actions authorize again.
*/

Route::middleware('app')->prefix('catalog')->name('catalog.')->group(function () {
    //
});
