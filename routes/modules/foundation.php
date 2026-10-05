<?php

use App\Modules\Foundation\Livewire\Admin\Users;
use Illuminate\Support\Facades\Route;

/*
| Foundation & Administration routes (docs/01 §5). Each admin route repeats its permission as
| `can:` middleware; Livewire actions authorize again.
*/

Route::middleware('app')->prefix('admin')->name('admin.')->group(function () {
    // Admin screens are added here by the tasks that build them.
    Route::livewire('users', Users\Index::class)->middleware('can:admin.users.view')->name('users.index');
    Route::livewire('users/create', Users\Form::class)->middleware('can:admin.users.create')->name('users.create');
    Route::livewire('users/{user:username}/edit', Users\Form::class)->middleware('can:admin.users.update')->name('users.edit');
});
