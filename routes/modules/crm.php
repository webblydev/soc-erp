<?php

use App\Modules\Crm\Livewire\Teams;
use Illuminate\Support\Facades\Route;

/*
| CRM routes (docs/03 §5). Each route repeats its permission or policy as `can:` middleware;
| Livewire actions and Actions authorize again.
*/

Route::middleware('app')->prefix('crm')->name('crm.')->group(function () {
    Route::livewire('teams', Teams\Index::class)->middleware('can:crm.teams.view')->name('teams.index');
    Route::livewire('teams/create', Teams\Form::class)->middleware('can:crm.teams.manage')->name('teams.create');
    Route::livewire('teams/{team}/edit', Teams\Form::class)->middleware('can:crm.teams.manage')->name('teams.edit');
});
