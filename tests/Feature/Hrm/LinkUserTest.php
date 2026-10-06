<?php

use App\Models\User;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Livewire\Admin\Users\Form;
use App\Modules\Hrm\Actions\LinkUser;
use App\Modules\Hrm\Actions\UnlinkUser;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    seedHrm();
    seedAccessControl();
    $this->admin = userWithPermissions('admin.users.view', 'admin.users.create', 'admin.users.update', 'hrm.employees.view_basic');
});

test('linking and unlinking a user from the employee side', function () {
    $employee = Employee::factory()->create();
    $user = User::factory()->create();

    app(LinkUser::class)->handle($this->admin, $employee, $user);
    expect($user->fresh()->employee_id)->toBe($employee->id);

    app(UnlinkUser::class)->handle($this->admin, $employee);
    expect($user->fresh()->employee_id)->toBeNull();
});

test('linking needs admin.users.update', function () {
    expect(fn () => app(LinkUser::class)->handle(userWithPermissions('hrm.employees.view_basic'), Employee::factory()->create(), User::factory()->create()))
        ->toThrow(AuthorizationException::class);
});

test('a user linked elsewhere cannot be linked again (FD-BR-03)', function () {
    $first = Employee::factory()->create();
    $second = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $first->id]);

    expectValidationError(fn () => app(LinkUser::class)->handle($this->admin, $second, $user), 'user_id');
    expectValidationError(fn () => app(LinkUser::class)->handle($this->admin, $first, User::factory()->create()), 'user_id');
});

test('the user form refuses an employee already linked to another user', function () {
    $employee = Employee::factory()->create();
    User::factory()->create(['employee_id' => $employee->id]);
    $other = User::factory()->create();
    $other->syncRoles(['engineer']);

    expectValidationError(fn () => app(UpdateUser::class)->handle($other, ['name' => $other->name, 'username' => $other->username, 'roles' => ['engineer'], 'employee_id' => $employee->id], superAdmin()), 'employee_id');
});

test('the user form offers unlinked employees and fills empty fields', function () {
    $employee = Employee::factory()->create(['full_name' => 'Nasrin Akter', 'official_email' => 'nasrin@soc.test', 'phone' => '01712000111']);
    $taken = Employee::factory()->create(['full_name' => 'Taken Person']);
    User::factory()->create(['employee_id' => $taken->id]);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->assertSee('Nasrin Akter')->assertDontSee('Taken Person')
        ->set('employee_id', (string) $employee->id)
        ->assertSet('name', 'Nasrin Akter')->assertSet('email', 'nasrin@soc.test')->assertSet('phone', '01712000111');
});

test('create user from an employee preselects it and saves the link', function () {
    $employee = Employee::factory()->create(['employee_code' => 'EMP-0007', 'full_name' => 'Jamal Uddin']);
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('admin.users.create', ['employee' => 'EMP-0007']))->assertOk()->assertSee('Jamal Uddin');

    Livewire::withQueryParams(['employee' => 'EMP-0007'])->actingAs($admin)->test(Form::class)
        ->assertSet('employee_id', $employee->id)
        ->assertSet('name', 'Jamal Uddin')
        ->set('username', 'jamal')->set('roles', ['engineer'])
        ->set('password', 'Secret-pass-123!')->set('password_confirmation', 'Secret-pass-123!')
        ->call('save')->assertHasNoErrors();

    expect(User::query()->where('username', 'jamal')->value('employee_id'))->toBe($employee->id);
});
