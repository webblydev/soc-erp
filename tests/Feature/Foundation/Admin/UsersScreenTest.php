<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\Users\Form;
use App\Modules\Foundation\Livewire\Admin\Users\Index;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(fn () => ensureRole('accountant'));

test('the users list needs admin.users.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.users.view'))
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee(route('admin.users.index'));
});

test('the list searches and filters by role', function () {
    $accountant = User::factory()->create(['name' => 'Accounts Person', 'username' => 'acc1']);
    $accountant->syncRoles(['accountant']);
    User::factory()->create(['name' => 'Someone Else', 'username' => 'other1']);

    Livewire::actingAs(userWithPermissions('admin.users.view'))
        ->test(Index::class)
        ->set('filters.role', 'accountant')
        ->assertSee('acc1')
        ->assertDontSee('other1')
        ->set('filters.role', '')
        ->set('search', 'someone')
        ->assertSee('other1')
        ->assertDontSee('acc1');
});

test('toggling active needs admin.users.deactivate even when called directly', function () {
    $target = User::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.users.view'))
        ->test(Index::class)
        ->call('toggleActive', $target->id)
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

test('deactivating the last super admin shows an error toast (FD-AC-03)', function () {
    $onlySuperAdmin = superAdmin();

    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.deactivate'))
        ->test(Index::class)
        ->call('toggleActive', $onlySuperAdmin->id)
        ->assertDispatched('toast', type: 'error');

    expect($onlySuperAdmin->fresh()->is_active)->toBeTrue();
});

test('the users list exports to excel', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.users.view'))->test(Index::class)->call('export');

    Excel::assertDownloaded('/^users-\d{8}-\d{6}\.xlsx$/');
});

test('an admin creates a user from the form', function () {
    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.create'))
        ->test(Form::class)
        ->set('name', 'Karim Mia')
        ->set('username', 'Karim')
        ->set('phone', '01812345678')
        ->set('roles', ['accountant'])
        ->set('password', 'secret-pass')
        ->set('password_confirmation', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('username', 'karim')->first()?->hasRole('accountant'))->toBeTrue();
});

test('form errors show on the fields', function () {
    User::factory()->create(['username' => 'taken']);

    Livewire::actingAs(userWithPermissions('admin.users.create'))
        ->test(Form::class)
        ->set('name', 'X')
        ->set('username', 'TAKEN')
        ->call('save')
        ->assertHasErrors(['username', 'roles', 'password']);
});

test('the edit form is reached by username and prefilled', function () {
    $user = User::factory()->create(['username' => 'karim', 'name' => 'Karim Mia']);
    $user->syncRoles(['accountant']);

    $this->actingAs(userWithPermissions('admin.users.update'))
        ->get('/admin/users/karim/edit')
        ->assertOk()
        ->assertSee('Karim Mia')
        ->assertSee('data-test="mobile-action-bar"', false)
        ->assertDontSee('data-test="mobile-bottom-nav"', false);
});

test('an accountant created by an admin must change password and sees no admin menus (FD-AC-01)', function () {
    seedAccessControl();

    Livewire::actingAs(userWithPermissions('admin.users.view', 'admin.users.create'))
        ->test(Form::class)
        ->set('name', 'Accounts One')
        ->set('username', 'accounts1')
        ->set('roles', ['accountant'])
        ->set('password', 'secret-pass')
        ->set('password_confirmation', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors();

    $accountant = User::query()->where('username', 'accounts1')->firstOrFail();

    $this->actingAs($accountant)->get(route('dashboard'))->assertRedirect(route('password.change'));

    $accountant->forceFill(['must_change_password' => false])->save();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.users.index'));
});
