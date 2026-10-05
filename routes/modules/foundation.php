<?php

use App\Modules\Foundation\Livewire\Admin\Company;
use App\Modules\Foundation\Livewire\Admin\Locations;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Livewire\Admin\Roles;
use App\Modules\Foundation\Livewire\Admin\Settings;
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
    Route::livewire('roles', Roles\Index::class)->middleware('can:admin.roles.view')->name('roles.index');
    Route::livewire('roles/create', Roles\Form::class)->middleware('can:admin.roles.create')->name('roles.create');
    Route::livewire('roles/{role:code}/edit', Roles\Form::class)->middleware('can:admin.roles.update')->name('roles.edit');
    // Master data carries no `can:` middleware: the permission is per table, so mount() and every action authorize it.
    Route::livewire('master-data', MasterData::class)->name('master-data.index');
    Route::livewire('master-data/{table}', MasterData::class)->name('master-data.show');
    Route::livewire('locations', Locations::class)->middleware('can:admin.locations.view')->name('locations.index');
    Route::livewire('company', Company::class)->middleware('can:admin.company.view')->name('company.edit');
    Route::livewire('settings', Settings::class)->middleware('can:admin.settings.view')->name('settings.edit');
    Route::redirect('branches', '/admin/master-data/branches')->middleware('can:admin.branches.view')->name('branches.index');
});
