<?php

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteRole;
use App\Modules\Foundation\Actions\SaveRole;
use App\Modules\Foundation\Livewire\Admin\Roles\Form;
use App\Modules\Foundation\Livewire\Admin\Roles\Index;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Services\PermissionRegistrar;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    createPermissions('admin.users.view', 'admin.users.create', 'admin.roles.view');
    $this->roleAdmin = userWithPermissions('admin.users.view', 'admin.users.create', 'admin.roles.view', 'admin.roles.create', 'admin.roles.update', 'admin.roles.delete');
    $this->actingAs($this->roleAdmin);
});

test('saving a role syncs its permissions and takes effect immediately', function () {
    $role = app(SaveRole::class)->handle(['name' => 'Auditor', 'code' => 'auditor', 'is_active' => true, 'permissions' => ['admin.users.view']], $this->roleAdmin);
    $member = User::factory()->create();
    $member->syncRoles(['auditor']);

    expect($member->can('admin.users.view'))->toBeTrue();

    app(SaveRole::class)->handle(['name' => 'Auditor', 'code' => 'auditor', 'is_active' => true, 'permissions' => ['admin.roles.view']], $this->roleAdmin, $role);

    expect($member->fresh()->can('admin.users.view'))->toBeFalse()
        ->and($member->fresh()->can('admin.roles.view'))->toBeTrue();
});

test('codes are snake case and unique', function () {
    ensureRole('auditor');

    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'X', 'code' => 'Bad Code'], $this->roleAdmin), 'code');
    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'X', 'code' => 'auditor'], $this->roleAdmin), 'code');
});

test('system role codes are locked and super admin is read-only', function () {
    $system = ensureRole('viewer', ['is_system' => true]);
    $super = ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);

    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'Viewer', 'code' => 'reader'], $this->roleAdmin, $system), 'code');
    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'Boss', 'code' => Role::SUPER_ADMIN], $this->roleAdmin, $super), 'role');
});

test('system roles and roles in use cannot be deleted', function () {
    $system = ensureRole('viewer', ['is_system' => true]);
    $held = ensureRole('auditor');
    User::factory()->create()->syncRoles(['auditor']);
    $unused = ensureRole('temp');

    expectValidationError(fn () => app(DeleteRole::class)->handle($system), 'role');
    expectValidationError(fn () => app(DeleteRole::class)->handle($held), 'role');

    app(DeleteRole::class)->handle($unused);
    expect(Role::query()->where('code', 'temp')->exists())->toBeFalse();
});

test('the roles list needs admin.roles.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.roles.view'))->get(route('admin.roles.index'))->assertOk();
});

test('the roles list component refuses users without admin.roles.view', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertForbidden();
});

test('deleting from the list needs admin.roles.delete even when called directly', function () {
    $role = ensureRole('temp');

    $admin = userWithPermissions('admin.roles.view', 'admin.roles.delete');
    $component = Livewire::actingAs($admin)->test(Index::class)->call('confirmDelete', $role->id);

    $admin->syncDirectPermissions(['admin.roles.view']);
    app(PermissionRegistrar::class)->forget($admin);

    $component->call('delete')->assertForbidden();

    expect(Role::query()->where('code', 'temp')->exists())->toBeTrue();
});

test('opening the delete confirmation needs admin.roles.delete', function () {
    $role = ensureRole('temp');

    Livewire::actingAs(userWithPermissions('admin.roles.view'))
        ->test(Index::class)
        ->call('confirmDelete', $role->id)
        ->assertForbidden();
});

test('deleting a role in use shows an error toast', function () {
    $role = ensureRole('auditor');
    User::factory()->create()->syncRoles(['auditor']);

    Livewire::test(Index::class)
        ->call('confirmDelete', $role->id)
        ->call('delete')
        ->assertDispatched('toast', type: 'error');
});

test('an unused role is deleted from the list', function () {
    $role = ensureRole('temp');

    Livewire::test(Index::class)
        ->call('confirmDelete', $role->id)
        ->call('delete')
        ->assertDispatched('toast', type: 'success');

    $this->assertSoftDeleted($role);
});

test('the matrix toggles a whole resource row and a whole action column', function () {
    Livewire::test(Form::class)
        ->call('toggleResource', 'admin', 'users')
        ->assertSet('permissions', ['admin.users.view', 'admin.users.create'])
        ->call('toggleResource', 'admin', 'users')
        ->assertSet('permissions', [])
        ->call('toggleAction', 'admin', 'view')
        ->assertSet('permissions', ['admin.users.view', 'admin.roles.view']);
});

test('a role is created from the form', function () {
    Livewire::test(Form::class)
        ->set('name', 'Auditor')
        ->set('code', 'auditor')
        ->set('permissions', ['admin.users.view'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::query()->where('code', 'auditor')->first()->permissions()->pluck('name')->all())->toBe(['admin.users.view']);
});

test('the super admin role opens read-only', function () {
    ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);

    $this->get(route('admin.roles.edit', Role::SUPER_ADMIN))
        ->assertOk()
        ->assertSee(__('Super admin has every permission and cannot be edited.'));
});

test('saving the read-only super admin role from the form is refused', function () {
    $super = ensureRole(Role::SUPER_ADMIN, ['is_system' => true]);

    Livewire::test(Form::class, ['role' => $super])
        ->set('name', 'Boss')
        ->call('save')
        ->assertHasErrors('role');
});

test('matrix toggles re-authorize when the permission is revoked after mount', function () {
    $user = userWithPermissions('admin.roles.create');
    $component = Livewire::actingAs($user)->test(Form::class);

    $user->syncDirectPermissions([]);
    app(PermissionRegistrar::class)->forget($user);

    $component->call('toggleResource', 'admin', 'users')->assertForbidden();
});

test('matrix column toggles re-authorize when the permission is revoked after mount', function () {
    $user = userWithPermissions('admin.roles.create');
    $component = Livewire::actingAs($user)->test(Form::class);

    $user->syncDirectPermissions([]);
    app(PermissionRegistrar::class)->forget($user);

    $component->call('toggleAction', 'admin', 'view')->assertForbidden();
});

test('a non super admin can only add permissions they hold to a role', function () {
    createPermissions('sales.quotes.approve');

    expectValidationError(fn () => app(SaveRole::class)->handle(['name' => 'Approver', 'code' => 'approver', 'permissions' => ['sales.quotes.approve']], $this->roleAdmin), 'permissions');

    expect(Role::query()->where('code', 'approver')->exists())->toBeFalse();
});

test('a super admin can put any permission into a role', function () {
    createPermissions('sales.quotes.approve');

    $role = app(SaveRole::class)->handle(['name' => 'Approver', 'code' => 'approver', 'permissions' => ['sales.quotes.approve']], superAdmin());

    expect($role->permissions()->pluck('name')->all())->toBe(['sales.quotes.approve']);
});

test('the role being deleted cannot be chosen from the client', function () {
    Livewire::test(Index::class)->set('deletingRoleId', ensureRole('auditor')->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('the roles table shows edit and delete in its actions column', function () {
    $role = ensureRole('auditor');

    Livewire::test(Index::class)
        ->assertSeeHtml('data-test="row-actions"')
        ->assertSeeHtml('href="'.route('admin.roles.edit', $role).'"')
        ->assertSeeHtml('wire:click="confirmDelete('.$role->id.')"');
});

test('bulk delete removes custom roles and skips system roles', function () {
    $system = ensureRole('auditor', ['is_system' => true]);
    $custom = ensureRole('temporary');

    Livewire::actingAs(superAdmin())->test(Index::class)
        ->set('selected', [(string) $system->id, (string) $custom->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning', description: '1 deleted, 1 skipped. System roles cannot be deleted.');

    expect(Role::query()->whereKey([$system->id, $custom->id])->pluck('code')->all())->toBe(['auditor']);
});
