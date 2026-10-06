<?php

use App\Models\User;
use App\Modules\Foundation\Actions\UpdateCompanyProfile;
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

test('logos must be png or jpg up to 20 MB', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->create('logo.png', 20481, 'image/png'))
        ->call('save')
        ->assertHasErrors(['logo' => 'max']);
});

test('logos up to 20 MB are accepted', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80)->size(20480))
        ->call('save')
        ->assertHasNoErrors();
});

test('saving without update permission is forbidden', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view'))->test(Company::class)->call('save')->assertForbidden();
});

test('a failed save removes the newly stored logo', function () {
    CompanyProfile::saving(fn () => throw new RuntimeException('boom'));

    $input = CompanyProfile::current()->only(UpdateCompanyProfile::FIELDS);

    expect(fn () => app(UpdateCompanyProfile::class)->handle($input, UploadedFile::fake()->image('logo.png', 200, 80)))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles('company'))->toBeEmpty();
});

test('view-only users cannot upload a logo', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))
        ->assertForbidden();
});

test('a gif logo is rejected and nothing is stored', function () {
    Livewire::actingAs(userWithPermissions('admin.company.view', 'admin.company.update'))
        ->test(Company::class)
        ->set('logo', UploadedFile::fake()->image('logo.gif', 200, 80))
        ->call('save')
        ->assertHasErrors(['logo']);

    expect(Storage::disk('public')->allFiles('company'))->toBeEmpty();
});
