<?php

use App\Modules\Foundation\Actions\StopImpersonation;
use App\Modules\Foundation\Livewire\Admin\AuditLog;
use App\Modules\Foundation\Livewire\Admin\Company;
use App\Modules\Foundation\Livewire\Admin\Locations;
use App\Modules\Foundation\Livewire\Admin\LoginHistory;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Livewire\Admin\Roles;
use App\Modules\Foundation\Livewire\Admin\Sequences;
use App\Modules\Foundation\Livewire\Admin\Settings;
use App\Modules\Foundation\Livewire\Admin\Users;
use App\Modules\Foundation\Livewire\Notifications;
use App\Modules\Foundation\Livewire\Profile;
use App\Modules\Foundation\Models\Attachment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
    Route::livewire('sequences', Sequences::class)->middleware('can:admin.sequences.view')->name('sequences.index');
    Route::livewire('audit', AuditLog::class)->middleware('can:admin.audit.view')->name('audit.index');
    Route::livewire('login-history', LoginHistory::class)->middleware('can:admin.login_history.view')->name('login-history.index');
    Route::redirect('branches', '/admin/master-data/branches')->middleware('can:admin.branches.view')->name('branches.index');
});

Route::middleware('app')->group(function () {
    Route::livewire('profile', Profile\Edit::class)->name('profile.edit');
    Route::livewire('notifications', Notifications\Index::class)->name('notifications.index');
    Route::livewire('two-factor/setup', Profile\TwoFactorSetup::class)->name('two-factor.setup');
    Route::get('attachments/{attachment}/download', function (Attachment $attachment) {
        abort_unless($attachment->attachable?->isViewableBy(request()->user()), 403);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    })->middleware('signed')->name('attachments.download');
    Route::post('impersonation/stop', function (StopImpersonation $stopImpersonation) {
        $stopImpersonation->handle();

        return redirect()->route('admin.users.index');
    })->name('impersonation.stop');
});
