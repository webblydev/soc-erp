<?php

use Illuminate\Support\Facades\Route;

/*
| CRM routes (docs/03 §5). Each route repeats its permission or policy as `can:` middleware;
| Livewire actions and Actions authorize again.
*/

Route::middleware('app')->prefix('crm')->name('crm.')->group(function () {
    // Screens are added by the tasks that build them.
});
