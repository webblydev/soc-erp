<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Company;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\CompanyProfile;
use Database\Seeders\Foundation\CompanyProfileSeeder;
use Database\Seeders\Foundation\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CurrencySeeder::class, CompanyProfileSeeder::class]);
    Storage::fake('public');
});

test('the company page needs admin.company.view and is read-only without update', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.company.edit'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.company.view'))
        ->get(route('admin.company.edit'))
        ->assertOk()
        ->assertSee('SOC Consultant')
        ->assertDontSee(__('Save company profile'));
});

test('saving updates the profile, audits it and shows TIN and BIN on the letterhead', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('tin', '123456789012')
        ->set('bin', '000111222-0101')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('123456789012')
        ->assertSee('000111222-0101');

    expect(CompanyProfile::current()->tin)->toBe('123456789012')
        ->and(AuditLog::query()->where('auditable_type', 'company')->where('event', 'updated')->exists())->toBeTrue();
});

test('a new logo replaces the old file', function () {
    $component = Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))->test(Company::class);

    $component->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))->call('save')->assertHasNoErrors();
    $first = CompanyProfile::current()->logo_path;
    Storage::disk('public')->assertExists($first);

    $component->set('logo', UploadedFile::fake()->image('logo2.jpg', 200, 80))->call('save')->assertHasNoErrors();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists(CompanyProfile::current()->logo_path);
});

test('logos must be png or jpg up to 1 MB', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->create('logo.png', 2048, 'image/png'))
        ->call('save')
        ->assertHasErrors(['logo']);
});

test('saving without update permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view'))->test(Company::class)->call('save')->assertForbidden();
});
