<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('app')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('password/change', 'pages::auth.change-password')->name('password.change');
});

require __DIR__.'/modules/foundation.php';
require __DIR__.'/settings.php';
