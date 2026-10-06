<?php

use Illuminate\Support\Facades\Route;

/*
| The starter's settings pages were folded into /profile (docs/01 §5.13). Old links redirect.
*/

Route::middleware('app')->group(function () {
    Route::redirect('settings', '/profile');
    Route::redirect('settings/profile', '/profile');
    Route::redirect('settings/security', '/profile?tab=password');
});
